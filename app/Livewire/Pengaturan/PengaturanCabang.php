<?php

namespace App\Livewire\Pengaturan;

use App\Models\Cabang;
use App\Models\Pengaturan;
use App\Support\KonteksCabang;
use App\Support\PengaturanBengkel;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan operasional per cabang (servis bengkel).
 * Owner bisa mengatur nilai default (berlaku semua cabang) atau per cabang; admin cabang hanya cabangnya.
 */
#[Title('Pengaturan')]
class PengaturanCabang extends Component
{
    /** '' = default semua cabang (khusus owner), atau id cabang */
    public string $lingkup = '';

    public bool $biaya_cek_aktif = false;

    public int|string $biaya_cek_nominal = 0;

    public int|string $batas_hari_ambil = PengaturanBengkel::DEFAULT_BATAS_HARI_AMBIL;

    public function mount(): void
    {
        $this->authorize('akses-admin');
        $saya = auth()->user();

        $this->lingkup = $saya->isOwner()
            ? (string) (KonteksCabang::filterOwner() ?? '')
            : (string) $saya->cabang_id;

        $this->muat();
    }

    public function updatedLingkup(): void
    {
        $this->muat();
    }

    protected function cabangId(): ?int
    {
        $saya = auth()->user();

        if (! $saya->isOwner()) {
            return $saya->cabang_id; // admin cabang selalu cabangnya sendiri
        }

        return $this->lingkup === '' ? null : (int) Cabang::query()->findOrFail((int) $this->lingkup)->id;
    }

    protected function muat(): void
    {
        $id = $this->cabangId();

        $this->biaya_cek_aktif = (bool) (int) Pengaturan::ambil(PengaturanBengkel::BIAYA_CEK_AKTIF, '0', $id);
        $this->biaya_cek_nominal = (int) Pengaturan::ambil(PengaturanBengkel::BIAYA_CEK_NOMINAL, '0', $id);
        $this->batas_hari_ambil = (int) Pengaturan::ambil(PengaturanBengkel::BATAS_HARI_AMBIL, (string) PengaturanBengkel::DEFAULT_BATAS_HARI_AMBIL, $id);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        $this->authorize('akses-admin');

        $this->validate([
            'biaya_cek_aktif' => ['boolean'],
            'biaya_cek_nominal' => ['required', 'integer', 'min:0', 'max:100000000'],
            'batas_hari_ambil' => ['required', 'integer', 'min:1', 'max:365'],
        ], attributes: [
            'biaya_cek_nominal' => 'nominal biaya cek',
            'batas_hari_ambil' => 'batas hari pengambilan',
        ]);

        if ($this->biaya_cek_aktif && (int) $this->biaya_cek_nominal <= 0) {
            $this->addError('biaya_cek_nominal', 'Isi nominal biaya cek, atau nonaktifkan biaya cek.');

            return;
        }

        $id = $this->cabangId();
        Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_AKTIF, $this->biaya_cek_aktif ? '1' : '0', $id);
        Pengaturan::simpan(PengaturanBengkel::BIAYA_CEK_NOMINAL, (string) (int) $this->biaya_cek_nominal, $id);
        Pengaturan::simpan(PengaturanBengkel::BATAS_HARI_AMBIL, (string) (int) $this->batas_hari_ambil, $id);

        Flux::toast(variant: 'success', text: 'Pengaturan tersimpan.');
    }

    public function render()
    {
        return view('livewire.pengaturan.pengaturan-cabang', [
            'pilihanCabang' => auth()->user()->isOwner() ? Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama']) : collect(),
            'namaCabang' => auth()->user()->isOwner() ? null : auth()->user()->cabang?->nama,
        ]);
    }
}
