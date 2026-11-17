<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Models\User;
use App\Notifications\JobBaruDitugaskan;
use App\Support\Jarak;
use App\Support\KirimNotifikasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membagi order kunjungan yang belum punya tim pada satu tanggal.
 *
 * Aturan (disepakati dengan pemilik usaha):
 *  1. Order diproses urut waktu input admin (paling awal duluan).
 *  2. Selama masih ada tim yang BELUM bergerak hari itu, order diberikan ke tim dengan jumlah job paling sedikit
 *     (dibagi rata ke semua tim).
 *  3. Jika SEMUA tim sudah bergerak (punya job berstatus berangkat/dikerjakan/selesai hari itu), order diberikan
 *     ke tim yang lokasi job TERAKHIR di antriannya paling dekat dengan lokasi order, lalu ditaruh di akhir antrian.
 *     Jika lokasi order/tim tidak diketahui, kembali ke aturan 2.
 * Jarak = jarak lurus (haversine), bukan jarak tempuh jalan.
 */
class PembagianTim
{
    public function __construct(private OrderService $orderService) {}

    /**
     * @return list<array{order: OrderServis, tim: ?Tim, alasan: string, jarak_km: ?float}>
     */
    public function usulan(int $cabangId, string $tanggal): array
    {
        $timAktif = Tim::query()->withoutGlobalScopes()
            ->where('cabang_id', $cabangId)->where('aktif', true)->whereNull('deleted_at')
            ->whereHas('anggotaAktif')
            ->orderBy('nama_tim')
            ->get()
            ->keyBy('id');

        $belumDibagi = $this->queryHari($cabangId, $tanggal)
            ->whereNull('tim_id')
            ->where('status', StatusOrder::Dijadwalkan->value)
            ->with('unit.pelanggan')
            ->urutAntrian()
            ->get();

        if ($timAktif->isEmpty()) {
            return $belumDibagi->map(fn ($o) => [
                'order' => $o, 'tim' => null, 'alasan' => 'Belum ada tim aktif yang punya anggota.', 'jarak_km' => null,
            ])->all();
        }

        // Kondisi awal tiap tim hari itu.
        $sudahDibagi = $this->queryHari($cabangId, $tanggal)
            ->whereIn('tim_id', $timAktif->keys())
            ->tidakBatal()
            ->with('unit.pelanggan')
            ->urutAntrian()
            ->get()
            ->groupBy('tim_id');

        $beban = [];
        $bergerak = [];
        $posisiTerakhir = [];

        foreach ($timAktif as $id => $tim) {
            /** @var Collection<int, OrderServis> $antrian */
            $antrian = $sudahDibagi->get($id, collect());
            $beban[$id] = $antrian->count();
            $bergerak[$id] = $antrian->contains(fn ($o) => in_array($o->status, StatusOrder::tandaBergerak(), true));
            $posisiTerakhir[$id] = $antrian->reverse()->map(fn ($o) => $o->koordinat())->first(fn ($k) => $k !== null);
        }

        $hasil = [];

        foreach ($belumDibagi as $order) {
            $semuaBergerak = ! in_array(false, $bergerak, true);
            $lokasiOrder = $order->koordinat();
            $pilihan = null;
            $jarak = null;

            if ($semuaBergerak && $lokasiOrder) {
                foreach ($posisiTerakhir as $id => $posisi) {
                    if ($posisi === null) {
                        continue;
                    }
                    $km = Jarak::km($posisi, $lokasiOrder);
                    if ($jarak === null || $km < $jarak) {
                        [$pilihan, $jarak] = [$id, $km];
                    }
                }
            }

            if ($pilihan !== null) {
                $alasan = 'Semua tim sudah bergerak; lokasi job terakhir tim ini paling dekat.';
            } else {
                $pilihan = $this->bebanTerkecil($beban);
                $alasan = $semuaBergerak
                    ? 'Semua tim sudah bergerak, tapi lokasi belum lengkap; dibagi rata.'
                    : 'Dibagi rata ke tim dengan job paling sedikit.';
            }

            $hasil[] = [
                'order' => $order,
                'tim' => $timAktif[$pilihan],
                'alasan' => $alasan,
                'jarak_km' => $jarak !== null ? round($jarak, 1) : null,
            ];

            // Order masuk ke akhir antrian tim terpilih.
            $beban[$pilihan]++;
            if ($lokasiOrder) {
                $posisiTerakhir[$pilihan] = $lokasiOrder;
            }
        }

        return $hasil;
    }

    /**
     * Terapkan pembagian (setelah dicek/diubah admin) dan kirim notifikasi ke teknisi.
     *
     * @param  array<int, int>  $pembagian  order_id => tim_id
     * @return int jumlah order yang ditugaskan
     */
    public function terapkan(array $pembagian, User $oleh): int
    {
        $penerima = [];

        DB::transaction(function () use ($pembagian, $oleh, &$penerima) {
            foreach ($pembagian as $orderId => $timId) {
                $order = OrderServis::query()->findOrFail($orderId);
                $tim = Tim::query()->findOrFail($timId);

                foreach ($this->orderService->tetapkanTim($order, $tim, $oleh) as $userId) {
                    $penerima[$userId][] = $order->id;
                }
            }
        });

        $users = User::query()->with('pushSubscriptions')->whereIn('id', array_keys($penerima))->get();
        foreach ($users as $user) {
            KirimNotifikasi::aman($user, new JobBaruDitugaskan($penerima[$user->id]));
        }

        return count($pembagian);
    }

    private function queryHari(int $cabangId, string $tanggal)
    {
        return OrderServis::query()->withoutGlobalScopes()
            ->where('cabang_id', $cabangId)
            ->kunjungan()
            ->padaTanggal($tanggal);
    }

    /** @param  array<int, int>  $beban */
    private function bebanTerkecil(array $beban): int
    {
        $min = min($beban);

        // Seri: ambil tim pertama (urut nama) agar hasil konsisten.
        return array_search($min, $beban, true);
    }
}
