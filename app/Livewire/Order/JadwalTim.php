<?php

namespace App\Livewire\Order;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\Tim;
use App\Services\PembagianTim;
use App\Support\KonteksCabang;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Jadwal kunjungan per hari per tim + pembagian order ke tim ("Bagi tim" → pratinjau → Terapkan).
 */
#[Title('Jadwal Tim')]
class JadwalTim extends Component
{
    #[Url]
    public string $tanggal = '';

    #[Url(as: 'cabang')]
    public ?int $cabangId = null;

    /** Pratinjau pembagian: order_id => tim_id (bisa diubah admin sebelum diterapkan). */
    public array $usulan = [];

    /** @var array<int, array{alasan: string, jarak_km: ?float}> */
    public array $infoUsulan = [];

    public function mount(): void
    {
        $this->authorize('viewAny', OrderServis::class);
        $this->tanggal = $this->tanggal ?: today()->toDateString();
        $this->cabangId = $this->cabangAktif();
    }

    /** Owner tanpa filter memilih cabang di halaman ini; selain itu cabang mengikuti konteks. */
    protected function cabangAktif(): ?int
    {
        return KonteksCabang::idPembatas() ?: ($this->cabangId ?: Cabang::query()->aktif()->orderBy('nama')->value('id'));
    }

    public function updatedTanggal(): void
    {
        $this->reset('usulan', 'infoUsulan');
    }

    public function updatedCabangId(): void
    {
        $this->reset('usulan', 'infoUsulan');
    }

    public function geserHari(int $hari): void
    {
        $this->tanggal = now()->parse($this->tanggal)->addDays($hari)->toDateString();
        $this->updatedTanggal();
    }

    public function bagiTim(PembagianTim $pembagian): void
    {
        $this->authorize('create', OrderServis::class);

        $hasil = $pembagian->usulan($this->cabangAktif(), $this->tanggal);

        if ($hasil === []) {
            Flux::toast('Tidak ada order yang perlu dibagi pada tanggal ini.');

            return;
        }

        $this->usulan = [];
        $this->infoUsulan = [];
        foreach ($hasil as $baris) {
            $id = $baris['order']->id;
            $this->usulan[$id] = $baris['tim']?->id;
            $this->infoUsulan[$id] = ['alasan' => $baris['alasan'], 'jarak_km' => $baris['jarak_km']];
        }

        Flux::modal('pratinjau-bagi')->show();
    }

    public function terapkan(PembagianTim $pembagian): void
    {
        $this->authorize('create', OrderServis::class);

        $pembagian_ = array_filter($this->usulan, fn ($timId) => filled($timId));

        if ($pembagian_ === []) {
            throw ValidationException::withMessages(['usulan' => 'Belum ada order yang dipilihkan tim.']);
        }

        // Pastikan semua order & tim milik cabang yang sedang dilihat (scope cabang ikut berlaku).
        $cabang = $this->cabangAktif();
        $orderValid = OrderServis::query()->whereIn('id', array_keys($pembagian_))->where('cabang_id', $cabang)->count();
        $timValid = Tim::query()->whereIn('id', array_unique($pembagian_))->where('cabang_id', $cabang)->count();
        abort_unless($orderValid === count($pembagian_) && $timValid === count(array_unique($pembagian_)), 403);

        $jumlah = $pembagian->terapkan(array_map('intval', $pembagian_), auth()->user());

        $this->reset('usulan', 'infoUsulan');
        Flux::modal('pratinjau-bagi')->close();
        Flux::toast(variant: 'success', text: "{$jumlah} order dibagikan. Notifikasi dikirim ke teknisi.");
    }

    public function render()
    {
        $cabang = $this->cabangAktif();

        $tim = Tim::query()->aktif()->where('cabang_id', $cabang)
            ->with('anggotaAktif.teknisi:id,nama')
            ->orderBy('nama_tim')->get();

        $order = OrderServis::query()->kunjungan()
            ->where('cabang_id', $cabang)
            ->padaTanggal($this->tanggal)
            ->with(['pelanggan:id,nama,alamat,latitude,longitude', 'unit.jenisPerangkat:id,nama', 'unit.pelanggan:id,latitude,longitude'])
            ->urutAntrian()
            ->get();

        return view('livewire.order.jadwal-tim', [
            'daftarTim' => $tim,
            'perTim' => $order->whereNotNull('tim_id')->groupBy('tim_id'),
            'belumDibagi' => $order->whereNull('tim_id')->where('status', '!=', StatusOrder::Batal),
            'orderUsulan' => $order->whereIn('id', array_keys($this->usulan))->keyBy('id'),
            'pilihanCabang' => auth()->user()->isOwner() && ! KonteksCabang::filterOwner()
                ? Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama'])
                : collect(),
        ]);
    }
}
