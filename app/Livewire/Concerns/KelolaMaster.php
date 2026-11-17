<?php

namespace App\Livewire\Concerns;

use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Perilaku bersama halaman master: pencarian, filter aktif/nonaktif, paginasi,
 * modal form tambah/ubah, aktif/nonaktifkan, dan hapus (soft delete).
 *
 * Komponen pemakai wajib mendefinisikan:
 *  - modelClass(): string
 *  - kolomCari(): array
 *  - isiForm(Model $model): void   (mengisi properti form dari model)
 *  - kosongkanForm(): void
 */
trait KelolaMaster
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $cari = '';

    /** aktif | nonaktif | semua */
    #[Url(except: 'aktif')]
    public string $status = 'aktif';

    public ?int $editId = null;

    public string $namaModal = 'form-master';

    abstract protected function modelClass(): string;

    /** @return list<string> */
    abstract protected function kolomCari(): array;

    abstract protected function isiForm(Model $model): void;

    abstract protected function kosongkanForm(): void;

    public function updatingCari(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    protected function terapkanFilter(Builder $query): Builder
    {
        if ($this->status === 'aktif') {
            $query->where($query->getModel()->qualifyColumn('aktif'), true);
        } elseif ($this->status === 'nonaktif') {
            $query->where($query->getModel()->qualifyColumn('aktif'), false);
        }

        $cari = trim($this->cari);

        if ($cari !== '') {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($cari, $like) {
                foreach ($this->kolomCari() as $kolom) {
                    $q->orWhere($kolom, $like, '%'.$cari.'%');
                }
            });
        }

        return $query;
    }

    protected function cariModel(int $id): Model
    {
        // Global scope (CabangScope) ikut berlaku, jadi data cabang lain tidak akan ditemukan.
        return ($this->modelClass())::query()->findOrFail($id);
    }

    public function tambah(): void
    {
        $this->authorize('create', $this->modelClass());

        $this->resetValidation();
        $this->editId = null;
        $this->kosongkanForm();

        Flux::modal($this->namaModal)->show();
    }

    public function ubah(int $id): void
    {
        $model = $this->cariModel($id);
        $this->authorize('update', $model);

        $this->resetValidation();
        $this->editId = $model->getKey();
        $this->isiForm($model);

        Flux::modal($this->namaModal)->show();
    }

    public function toggleAktif(int $id): void
    {
        $model = $this->cariModel($id);
        $this->authorize('update', $model);

        $model->update(['aktif' => ! $model->aktif]);

        Flux::toast($model->aktif ? 'Data diaktifkan.' : 'Data dinonaktifkan.');
    }

    public function hapus(int $id): void
    {
        $model = $this->cariModel($id);
        $this->authorize('delete', $model);

        $model->delete();

        Flux::toast('Data dihapus.');
    }

    protected function selesaiSimpan(string $pesan = 'Data tersimpan.'): void
    {
        Flux::modal($this->namaModal)->close();
        Flux::toast(variant: 'success', text: $pesan);
        $this->editId = null;
    }
}
