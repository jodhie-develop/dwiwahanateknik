<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\IsiKoordinat;
use App\Livewire\Concerns\KelolaMaster;
use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pelanggan')]
class PelangganIndex extends Component
{
    use IsiKoordinat, KelolaMaster;

    public string $nama = '';

    public string $hp = '';

    public string $alamat = '';

    public string $catatan = '';

    public bool $aktif = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Pelanggan::class);
    }

    protected function modelClass(): string
    {
        return Pelanggan::class;
    }

    protected function kolomCari(): array
    {
        return ['nama', 'hp', 'alamat'];
    }

    protected function isiForm(Model $model): void
    {
        $this->nama = $model->nama;
        $this->hp = (string) $model->hp;
        $this->alamat = (string) $model->alamat;
        $this->catatan = (string) $model->catatan;
        $this->aktif = $model->aktif;
        $this->isiKoordinatDari($model);
    }

    protected function kosongkanForm(): void
    {
        $this->reset('nama', 'hp', 'alamat', 'catatan', 'aktif', 'linkMaps', 'latitude', 'longitude');
    }

    public function simpan(): void
    {
        if (! $this->prosesLinkMapsTertunda()) {
            return;
        }

        $this->hp = preg_replace('/[^0-9+]/', '', $this->hp);

        $data = $this->validate([
            'nama' => ['required', 'string', 'max:150'],
            'hp' => ['nullable', 'string', 'max:30', 'regex:/^(\+?62|0)8[0-9]{7,13}$/'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'aktif' => ['boolean'],
            ...$this->aturanKoordinat(),
        ], ['hp.regex' => 'Nomor HP/WA tidak valid. Contoh: 081234567890.'], ['hp' => 'nomor HP/WA']);

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editId) {
            $pelanggan = $this->cariModel($this->editId);
            $this->authorize('update', $pelanggan);
            $pelanggan->update($data);
            $this->selesaiSimpan();

            return;
        }

        $this->authorize('create', Pelanggan::class);
        $pelanggan = Pelanggan::create($data);
        $this->selesaiSimpan('Pelanggan ditambahkan. Silakan tambahkan unitnya.');

        $this->redirectRoute('master.pelanggan.detail', $pelanggan, navigate: true);
    }

    public function render()
    {
        $daftar = $this->terapkanFilter(Pelanggan::query())
            ->withCount(['unit' => fn ($q) => $q->where('aktif', true)])
            ->orderBy('nama')
            ->paginate(20);

        return view('livewire.master.pelanggan-index', compact('daftar'));
    }
}
