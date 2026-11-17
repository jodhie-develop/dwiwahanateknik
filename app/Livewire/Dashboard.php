<?php

namespace App\Livewire;

use App\Enums\StatusOrder;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Support\KonteksCabang;
use App\Support\PengaturanBengkel;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard admin: monitoring progres job kunjungan tiap tim pada satu hari.
 * Data ikut dibatasi cabang (admin cabang = cabangnya; owner = filter cabang / semua).
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url(except: '')]
    public string $tanggal = '';

    public function mount(): void
    {
        $this->authorize('akses-admin');
        $this->tanggal = $this->tanggal ?: today()->toDateString();
    }

    public function geserHari(int $hari): void
    {
        $this->tanggal = now()->parse($this->hariIni())->addDays($hari)->toDateString();
    }

    protected function hariIni(): string
    {
        return $this->tanggal ?: today()->toDateString();
    }

    public function render()
    {
        $tanggal = $this->hariIni();

        $daftarTim = Tim::query()->aktif()
            ->with(['cabang:id,nama', 'anggotaAktif' => fn ($q) => $q->orderByDesc('peran'), 'anggotaAktif.teknisi:id,nama'])
            ->orderBy('cabang_id')->orderBy('nama_tim')
            ->get();

        // Jumlah job per tim per status pada tanggal tsb (1 query).
        $rekap = OrderServis::query()->kunjungan()->padaTanggal($tanggal)
            ->whereNotNull('tim_id')
            ->selectRaw('tim_id, status, count(*) as jumlah')
            ->groupBy('tim_id', 'status')
            ->get()
            ->groupBy('tim_id');

        $box = $daftarTim->map(function (Tim $tim) use ($rekap) {
            $perStatus = $rekap->get($tim->id, collect())->mapWithKeys(fn ($r) => [
                ($r->status instanceof StatusOrder ? $r->status->value : $r->status) => (int) $r->jumlah,
            ]);

            $batal = $perStatus->get(StatusOrder::Batal->value, 0);
            $total = $perStatus->sum() - $batal;
            $selesai = $perStatus->get(StatusOrder::Selesai->value, 0);

            return [
                'tim' => $tim,
                'total' => $total,
                'selesai' => $selesai,
                'berjalan' => $perStatus->get(StatusOrder::Berangkat->value, 0) + $perStatus->get(StatusOrder::Dikerjakan->value, 0),
                'batal' => $batal,
                'persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            ];
        });

        $belumDibagi = OrderServis::query()->kunjungan()->padaTanggal($tanggal)
            ->whereNull('tim_id')->where('status', StatusOrder::Dijadwalkan->value)->count();

        // Ringkasan bengkel: barang yang masih ada di cabang, per status.
        $perStatusBengkel = OrderServis::query()->bengkel()
            ->whereNotIn('status', [StatusOrder::Diambil->value, StatusOrder::Dikembalikan->value])
            ->selectRaw('status, count(*) as jumlah')->groupBy('status')
            ->pluck('jumlah', 'status')
            ->mapWithKeys(fn ($j, $s) => [($s instanceof StatusOrder ? $s->value : $s) => (int) $j]);

        $cabang = KonteksCabang::idPembatas() ?? 0;
        $ringkasBengkel = collect([
            StatusOrder::Diterima, StatusOrder::Diagnosa, StatusOrder::MenungguKonfirmasi,
            StatusOrder::Dikerjakan, StatusOrder::MenungguSparepart, StatusOrder::Selesai, StatusOrder::Batal,
        ])->map(fn (StatusOrder $s) => [
            'status' => $s,
            'label' => match ($s) {
                StatusOrder::Selesai => 'Selesai, belum diambil',
                StatusOrder::Batal => 'Batal, belum dikembalikan',
                default => $s->label(),
            },
            'jumlah' => $perStatusBengkel->get($s->value, 0),
        ]);

        return view('livewire.dashboard', [
            'ringkasBengkel' => $ringkasBengkel,
            'belumDiambilLewat' => OrderServis::query()->belumDiambilLewat(PengaturanBengkel::batasHariAmbil($cabang))->count(),
            'batasHariAmbil' => PengaturanBengkel::batasHariAmbil($cabang),
            'box' => $box,
            'tanggalTampil' => $tanggal,
            'belumDibagi' => $belumDibagi,
            'tampilCabang' => auth()->user()->isOwner() && ! KonteksCabang::filterOwner(),
        ]);
    }
}
