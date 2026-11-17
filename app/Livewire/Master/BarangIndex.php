<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\KelolaMaster;
use App\Models\Barang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Barang')]
class BarangIndex extends Component
{
    use KelolaMaster;

    public string $kode = '';

    public string $nama = '';

    public string $satuan = 'pcs';

    public int|string $harga_jual = 0;

    public bool $aktif = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Barang::class);
    }

    protected function modelClass(): string
    {
        return Barang::class;
    }

    protected function kolomCari(): array
    {
        return ['kode', 'nama'];
    }

    protected function isiForm(Model $model): void
    {
        $this->fill($model->only(['kode', 'nama', 'satuan', 'harga_jual', 'aktif']));
    }

    protected function kosongkanForm(): void
    {
        $this->reset('kode', 'nama', 'satuan', 'harga_jual', 'aktif');
    }

    public function simpan(): void
    {
        $this->kode = Str::upper(trim($this->kode));

        $data = $this->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('barang', 'kode')->ignore($this->editId)],
            'nama' => ['required', 'string', 'max:150'],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_jual' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'aktif' => ['boolean'],
        ], attributes: ['harga_jual' => 'harga jual']);

        if ($this->editId) {
            $barang = $this->cariModel($this->editId);
            $this->authorize('update', $barang);
            $barang->update($data);
        } else {
            $this->authorize('create', Barang::class);
            Barang::create($data);
        }

        $this->selesaiSimpan();
    }

    public function render()
    {
        $daftar = $this->terapkanFilter(Barang::query())->orderBy('nama')->paginate(20);

        return view('livewire.master.barang-index', compact('daftar'));
    }
}
