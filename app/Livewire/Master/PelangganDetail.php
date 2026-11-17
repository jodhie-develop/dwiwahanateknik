<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\IsiKoordinat;
use App\Models\JenisPerangkat;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PelangganDetail extends Component
{
    use IsiKoordinat;

    public Pelanggan $pelanggan;

    public bool $tampilNonaktif = false;

    // --- Form unit ---
    public ?int $unitId = null;

    public ?int $jenis_perangkat_id = null;

    public string $merk = '';

    public string $model = '';

    public string $nomor_seri = '';

    public string $ukuran = '';

    public string $lokasi = '';

    public string $alamat = '';

    public bool $aktif = true;

    public function mount(Pelanggan $pelanggan): void
    {
        $this->authorize('view', $pelanggan);
        $this->pelanggan = $pelanggan;
    }

    protected function cariUnit(int $id): UnitPelanggan
    {
        return $this->pelanggan->unit()->findOrFail($id);
    }

    public function tambahUnit(): void
    {
        $this->authorize('create', UnitPelanggan::class);
        $this->resetValidation();
        $this->reset('unitId', 'jenis_perangkat_id', 'merk', 'model', 'nomor_seri', 'ukuran', 'lokasi', 'alamat', 'aktif', 'linkMaps', 'latitude', 'longitude');

        Flux::modal('form-unit')->show();
    }

    public function ubahUnit(int $id): void
    {
        $unit = $this->cariUnit($id);
        $this->authorize('update', $unit);
        $this->resetValidation();

        $this->unitId = $unit->id;
        $this->jenis_perangkat_id = $unit->jenis_perangkat_id;
        foreach (['merk', 'model', 'nomor_seri', 'ukuran', 'lokasi', 'alamat'] as $kolom) {
            $this->{$kolom} = (string) $unit->{$kolom};
        }
        $this->aktif = $unit->aktif;
        $this->isiKoordinatDari($unit);

        Flux::modal('form-unit')->show();
    }

    public function simpanUnit(): void
    {
        if (! $this->prosesLinkMapsTertunda()) {
            return;
        }

        $data = $this->validate([
            ...$this->aturanKoordinat(),
            'jenis_perangkat_id' => ['required', 'integer', Rule::exists('jenis_perangkat', 'id')->whereNull('deleted_at')],
            'merk' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'nomor_seri' => ['nullable', 'string', 'max:100'],
            'ukuran' => ['nullable', 'string', 'max:50'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'aktif' => ['boolean'],
        ], attributes: ['jenis_perangkat_id' => 'jenis perangkat', 'nomor_seri' => 'nomor seri']);

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->unitId) {
            $unit = $this->cariUnit($this->unitId);
            $this->authorize('update', $unit);
            $unit->update($data);
        } else {
            $this->authorize('create', UnitPelanggan::class);
            $this->pelanggan->unit()->create($data);
        }

        Flux::modal('form-unit')->close();
        Flux::toast(variant: 'success', text: 'Unit tersimpan.');
    }

    public function toggleAktifUnit(int $id): void
    {
        $unit = $this->cariUnit($id);
        $this->authorize('update', $unit);
        $unit->update(['aktif' => ! $unit->aktif]);
    }

    public function hapusUnit(int $id): void
    {
        $unit = $this->cariUnit($id);
        $this->authorize('delete', $unit);
        $unit->delete();

        Flux::toast('Unit dihapus.');
    }

    public function render()
    {
        $unit = $this->pelanggan->unit()
            ->when(! $this->tampilNonaktif, fn ($q) => $q->where('aktif', true))
            ->with('jenisPerangkat:id,nama')
            ->orderBy('jenis_perangkat_id')
            ->get();

        return view('livewire.master.pelanggan-detail', [
            'daftarUnit' => $unit,
            'pilihanJenis' => JenisPerangkat::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
        ])->title($this->pelanggan->nama);
    }
}
