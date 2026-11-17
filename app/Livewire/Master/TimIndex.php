<?php

namespace App\Livewire\Master;

use App\Enums\PeranTim;
use App\Livewire\Concerns\KelolaMaster;
use App\Models\Cabang;
use App\Models\Teknisi;
use App\Models\Tim;
use App\Support\KonteksCabang;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tim')]
class TimIndex extends Component
{
    use KelolaMaster;

    public ?int $cabang_id = null;

    public string $nama_tim = '';

    public bool $aktif = true;

    // --- Kelola anggota ---
    public ?int $timAnggotaId = null;

    public ?int $teknisi_id = null;

    public string $peran = 'anggota';

    public ?string $tgl_mulai = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Tim::class);
    }

    protected function modelClass(): string
    {
        return Tim::class;
    }

    protected function kolomCari(): array
    {
        return ['nama_tim'];
    }

    protected function isiForm(Model $model): void
    {
        $this->cabang_id = $model->cabang_id;
        $this->nama_tim = $model->nama_tim;
        $this->aktif = $model->aktif;
    }

    protected function kosongkanForm(): void
    {
        $this->reset('nama_tim', 'aktif');
        $this->cabang_id = KonteksCabang::idPembatas() ?: Cabang::query()->aktif()->value('id');
    }

    public function simpan(): void
    {
        $saya = auth()->user();

        if (! $saya->isOwner()) {
            $this->cabang_id = $saya->cabang_id;
        }

        $data = $this->validate([
            'cabang_id' => ['required', 'integer', Rule::exists('cabang', 'id')->whereNull('deleted_at')],
            'nama_tim' => ['required', 'string', 'max:100'],
            'aktif' => ['boolean'],
        ], attributes: ['nama_tim' => 'nama tim', 'cabang_id' => 'cabang']);

        if ($this->editId) {
            $tim = $this->cariModel($this->editId);
            $this->authorize('update', $tim);

            // Cabang tim tidak bisa dipindah, karena anggotanya terikat cabang.
            unset($data['cabang_id']);
            $tim->update($data);
        } else {
            $this->authorize('create', Tim::class);
            Tim::create($data);
        }

        $this->selesaiSimpan();
    }

    // ---------------- Anggota ----------------

    public function kelolaAnggota(int $timId): void
    {
        $tim = $this->cariModel($timId);
        $this->authorize('update', $tim);

        $this->timAnggotaId = $tim->id;
        $this->reset('teknisi_id', 'peran');
        $this->tgl_mulai = now()->toDateString();
        $this->resetValidation();

        Flux::modal('anggota-tim')->show();
    }

    public function tambahAnggota(): void
    {
        $tim = $this->cariModel($this->timAnggotaId);
        $this->authorize('update', $tim);

        $this->validate([
            'teknisi_id' => [
                'required', 'integer',
                Rule::exists('teknisi', 'id')->where('cabang_id', $tim->cabang_id)->whereNull('deleted_at'),
            ],
            'peran' => ['required', Rule::enum(PeranTim::class)],
            'tgl_mulai' => ['required', 'date'],
        ], attributes: ['teknisi_id' => 'teknisi', 'tgl_mulai' => 'tanggal mulai']);

        if ($tim->anggotaAktif()->where('teknisi_id', $this->teknisi_id)->exists()) {
            $this->addError('teknisi_id', 'Teknisi ini sudah menjadi anggota aktif tim.');

            return;
        }

        if ($this->peran === PeranTim::Ketua->value && $tim->anggotaAktif()->where('peran', PeranTim::Ketua->value)->exists()) {
            $this->addError('peran', 'Tim sudah punya ketua aktif. Keluarkan ketua lama terlebih dahulu.');

            return;
        }

        $tim->anggota()->create([
            'teknisi_id' => $this->teknisi_id,
            'peran' => $this->peran,
            'tgl_mulai' => $this->tgl_mulai,
        ]);

        $this->reset('teknisi_id', 'peran');
        Flux::toast(variant: 'success', text: 'Anggota ditambahkan.');
    }

    /** Anggota tidak dihapus agar riwayat tetap ada; cukup diberi tanggal selesai. */
    public function keluarkanAnggota(int $anggotaId): void
    {
        $tim = $this->cariModel($this->timAnggotaId);
        $this->authorize('update', $tim);

        $anggota = $tim->anggota()->findOrFail($anggotaId);
        $anggota->update(['tgl_selesai' => now()->toDateString()]);

        Flux::toast('Anggota dikeluarkan dari tim.');
    }

    public function render()
    {
        $daftar = $this->terapkanFilter(Tim::query())
            ->with(['cabang:id,nama', 'anggotaAktif.teknisi:id,nama'])
            ->orderBy('nama_tim')
            ->paginate(15);

        $timDikelola = $this->timAnggotaId
            ? Tim::query()->with(['anggotaAktif.teknisi:id,nama,bagian'])->find($this->timAnggotaId)
            : null;

        $pilihanTeknisi = $timDikelola
            ? Teknisi::query()->aktif()->where('cabang_id', $timDikelola->cabang_id)
                ->whereNotIn('id', $timDikelola->anggotaAktif->pluck('teknisi_id'))
                ->orderBy('nama')->get(['id', 'nama', 'bagian'])
            : collect();

        return view('livewire.master.tim-index', [
            'daftar' => $daftar,
            'pilihanCabang' => Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
            'timDikelola' => $timDikelola,
            'pilihanTeknisi' => $pilihanTeknisi,
            'pilihanPeran' => PeranTim::options(),
        ]);
    }
}
