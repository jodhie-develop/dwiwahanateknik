<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\KelolaMaster;
use App\Models\JenisPerangkat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Jenis Perangkat')]
class JenisPerangkatIndex extends Component
{
    use KelolaMaster;

    public string $nama = '';

    public bool $aktif = true;

    public function mount(): void
    {
        $this->authorize('viewAny', JenisPerangkat::class);
    }

    protected function modelClass(): string
    {
        return JenisPerangkat::class;
    }

    protected function kolomCari(): array
    {
        return ['nama'];
    }

    protected function isiForm(Model $model): void
    {
        $this->nama = $model->nama;
        $this->aktif = $model->aktif;
    }

    protected function kosongkanForm(): void
    {
        $this->reset('nama', 'aktif');
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('jenis_perangkat', 'nama')->ignore($this->editId)],
            'aktif' => ['boolean'],
        ]);

        if ($this->editId) {
            $model = $this->cariModel($this->editId);
            $this->authorize('update', $model);
            $model->update($data);
        } else {
            $this->authorize('create', JenisPerangkat::class);
            JenisPerangkat::create($data);
        }

        $this->selesaiSimpan();
    }

    public function render()
    {
        $daftar = $this->terapkanFilter(JenisPerangkat::query())
            ->withCount('layanan')
            ->orderBy('nama')
            ->paginate(15);

        return view('livewire.master.jenis-perangkat-index', compact('daftar'));
    }
}
