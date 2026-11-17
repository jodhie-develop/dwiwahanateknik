<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Titik-titik rute "Lihat Peta" untuk teknisi pada satu hari (job kunjungan).
 *
 * Aturan (permintaan pemilik usaha):
 *  - Belum mulai bekerja (belum ada job berangkat/dikerjakan/selesai): kantor cabang → semua order hari itu, urut antrian.
 *  - Sudah mengerjakan: titik order yang sedang dikerjakan (atau terakhir selesai) → order berikutnya di antrian.
 */
class RuteTeknisi
{
    /**
     * @return array{
     *   mode: 'awal'|'lanjut'|'kosong',
     *   titik: list<array{lat: float, lng: float, label: string, jenis: string, nomor: ?int, order_id: ?int, alamat: ?string}>,
     *   tanpaLokasi: list<string>,
     *   pesan: ?string
     * }
     */
    public function untuk(User $user, ?string $tanggal = null): array
    {
        $tanggal ??= today()->toDateString();

        // JobTeknisiScope membatasi ke job milik teknisi yang login; filter tambahan by user tetap dipasang
        // agar service aman dipanggil di luar konteks login.
        $job = OrderServis::query()->withoutGlobalScopes()
            ->kunjungan()
            ->padaTanggal($tanggal)
            ->tidakBatal()
            ->whereHas('teknisi.teknisi', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $user->id))
            ->with(['pelanggan:id,nama,alamat,latitude,longitude', 'unit.pelanggan:id,alamat,latitude,longitude'])
            ->urutAntrian()
            ->get()
            ->values();

        if ($job->isEmpty()) {
            return ['mode' => 'kosong', 'titik' => [], 'tanpaLokasi' => [], 'pesan' => 'Belum ada job hari ini.'];
        }

        $bergerak = $job->contains(fn ($o) => in_array($o->status, StatusOrder::tandaBergerak(), true));

        return $bergerak ? $this->ruteLanjut($job) : $this->ruteAwal($job, $user);
    }

    /** @param Collection<int, OrderServis> $job */
    private function ruteAwal(Collection $job, User $user): array
    {
        $titik = [];
        $pesan = null;
        $kantor = Cabang::query()->find($user->cabang_id);

        if ($k = $kantor?->koordinat()) {
            $titik[] = ['lat' => $k['lat'], 'lng' => $k['lng'], 'label' => 'Kantor '.$kantor->nama, 'jenis' => 'kantor', 'nomor' => null, 'order_id' => null, 'alamat' => $kantor->alamat];
        } else {
            $pesan = 'Lokasi kantor cabang belum diisi; rute dimulai dari job pertama.';
        }

        [$titikOrder, $tanpaLokasi] = $this->titikOrder($job, $job);

        return ['mode' => 'awal', 'titik' => [...$titik, ...$titikOrder], 'tanpaLokasi' => $tanpaLokasi, 'pesan' => $pesan];
    }

    /** @param Collection<int, OrderServis> $job */
    private function ruteLanjut(Collection $job): array
    {
        $aktif = $job->first(fn ($o) => in_array($o->status, [StatusOrder::Berangkat, StatusOrder::Dikerjakan], true))
            ?? $job->filter(fn ($o) => $o->status === StatusOrder::Selesai)->last();

        $posisi = $job->search(fn ($o) => $o->id === $aktif->id);
        $berikutnya = $job->slice($posisi + 1)->first(fn ($o) => $o->status === StatusOrder::Dijadwalkan)
            // order yang diinput lebih awal tapi belum dikerjakan (mis. dilompati) tetap dianggap berikutnya
            ?? $job->first(fn ($o) => $o->status === StatusOrder::Dijadwalkan);

        $pilih = collect([$aktif, $berikutnya])->filter();
        [$titik, $tanpaLokasi] = $this->titikOrder($pilih, $job);

        $pesan = $berikutnya ? null : 'Tidak ada job berikutnya hari ini.';

        return ['mode' => 'lanjut', 'titik' => $titik, 'tanpaLokasi' => $tanpaLokasi, 'pesan' => $pesan];
    }

    /**
     * @param  Collection<int, OrderServis>  $pilih  order yang dimasukkan ke rute
     * @param  Collection<int, OrderServis>  $semua  seluruh antrian (untuk nomor urut)
     * @return array{0: list<array>, 1: list<string>}
     */
    private function titikOrder(Collection $pilih, Collection $semua): array
    {
        $titik = [];
        $tanpaLokasi = [];

        foreach ($pilih as $o) {
            $nomor = $semua->search(fn ($x) => $x->id === $o->id) + 1;
            $k = $o->koordinat();

            if (! $k) {
                $tanpaLokasi[] = "{$nomor}. {$o->pelanggan?->nama}";

                continue;
            }

            $titik[] = [
                'lat' => $k['lat'], 'lng' => $k['lng'],
                'label' => "{$nomor}. {$o->pelanggan?->nama}",
                'jenis' => $o->status->terkunci() ? 'selesai' : (in_array($o->status, [StatusOrder::Berangkat, StatusOrder::Dikerjakan], true) ? 'aktif' : 'order'),
                'nomor' => $nomor,
                'order_id' => $o->id,
                'alamat' => $o->unit?->alamatEfektif(),
            ];
        }

        return [$titik, $tanpaLokasi];
    }

    /** Link Google Maps multi-titik (navigasi jalan sebenarnya di aplikasi Maps). */
    public static function linkGoogleMaps(array $titik): ?string
    {
        if (count($titik) < 2) {
            return count($titik) === 1
                ? 'https://www.google.com/maps/dir/?api=1&destination='.$titik[0]['lat'].','.$titik[0]['lng']
                : null;
        }

        $asal = array_shift($titik);
        $tujuan = array_pop($titik);
        $singgah = implode('|', array_map(fn ($t) => $t['lat'].','.$t['lng'], array_slice($titik, 0, 9)));

        return 'https://www.google.com/maps/dir/?api=1'
            .'&origin='.$asal['lat'].','.$asal['lng']
            .'&destination='.$tujuan['lat'].','.$tujuan['lng']
            .($singgah !== '' ? '&waypoints='.rawurlencode($singgah) : '')
            .'&travelmode=driving';
    }
}
