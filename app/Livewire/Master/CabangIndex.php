<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\IsiKoordinat;
use App\Livewire\Concerns\KelolaMaster;
use App\Models\Cabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cabang')]
class CabangIndex extends Component
{
    use IsiKoordinat, KelolaMaster;

    public string $kode = '';

    public string $nama = '';

    public string $alamat = '';

    public string $telp = '';

    public bool $aktif = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Cabang::class);
    }

    protected function modelClass(): string
    {
        return Cabang::class;
    }

    protected function kolomCari(): array
    {
        return ['kode', 'nama', 'alamat'];
    }

    protected function isiForm(Model $model): void
    {
        $this->fill($model->only(['kode', 'nama', 'aktif']));
        $this->alamat = (string) $model->alamat;
        $this->telp = (string) $model->telp;
        $this->isiKoordinatDari($model);
    }

    protected function kosongkanForm(): void
    {
        $this->reset('kode', 'nama', 'alamat', 'telp', 'aktif', 'linkMaps', 'latitude', 'longitude');
    }

    public function simpan(): void
    {
        if (! $this->prosesLinkMapsTertunda()) {
            return;
        }

        $this->kode = Str::upper(trim($this->kode));

        $data = $this->validate([
            'kode' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('cabang', 'kode')->ignore($this->editId)],
            'nama' => ['required', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telp' => ['nullable', 'string', 'max:30'],
            'aktif' => ['boolean'],
            ...$this->aturanKoordinat(),
        ], attributes: ['kode' => 'kode cabang']);

        if ($this->editId) {
            $cabang = $this->cariModel($this->editId);
            $this->authorize('update', $cabang);
            $cabang->update($data);
        } else {
            $this->authorize('create', Cabang::class);
            Cabang::create($data);
        }

        $this->selesaiSimpan();
    }

    public function render()
    {
        $daftar = $this->terapkanFilter(Cabang::query())
            ->withCount('users')
            ->orderBy('kode')
            ->paginate(15);

        return view('livewire.master.cabang-index', compact('daftar'));
    }
}
