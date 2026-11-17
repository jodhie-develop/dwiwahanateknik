<?php

namespace App\Livewire\Master;

use App\Enums\TipeLayanan;
use App\Livewire\Concerns\KelolaMaster;
use App\Models\Cabang;
use App\Models\JenisPerangkat;
use App\Models\Layanan;
use App\Models\LayananHarga;
use App\Support\KonteksCabang;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Layanan & Harga')]
class LayananIndex extends Component
{
    use KelolaMaster;

    #[Url(as: 'jenis', except: '')]
    public string $filterJenis = '';

    public ?int $jenis_perangkat_id = null;

    public string $nama = '';

    public string $tipe = 'keduanya';

    public int|string $masa_garansi_hari = 0;

    public int|string|null $interval_reminder_hari = null;

    public bool $aktif = true;

    // --- Harga per cabang ---
    public ?int $hargaLayananId = null;

    /** @var array<int|string, int|string|null> cabang_id => harga */
    public array $harga = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Layanan::class);
    }

    public function updatingFilterJenis(): void
    {
        $this->resetPage();
    }

    protected function modelClass(): string
    {
        return Layanan::class;
    }

    protected function kolomCari(): array
    {
        return ['layanan.nama'];
    }

    protected function isiForm(Model $model): void
    {
        $this->jenis_perangkat_id = $model->jenis_perangkat_id;
        $this->nama = $model->nama;
        $this->tipe = $model->tipe->value;
        $this->masa_garansi_hari = $model->masa_garansi_hari;
        $this->interval_reminder_hari = $model->interval_reminder_hari;
        $this->aktif = $model->aktif;
    }

    protected function kosongkanForm(): void
    {
        $this->reset('nama', 'tipe', 'masa_garansi_hari', 'interval_reminder_hari', 'aktif');
        $this->jenis_perangkat_id = $this->filterJenis !== '' ? (int) $this->filterJenis : null;
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'jenis_perangkat_id' => ['required', 'integer', Rule::exists('jenis_perangkat', 'id')->whereNull('deleted_at')],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::enum(TipeLayanan::class)],
            'masa_garansi_hari' => ['required', 'integer', 'min:0', 'max:3650'],
            'interval_reminder_hari' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'aktif' => ['boolean'],
        ], attributes: [
            'jenis_perangkat_id' => 'jenis perangkat',
            'masa_garansi_hari' => 'masa garansi',
            'interval_reminder_hari' => 'interval reminder',
        ]);

        $data['interval_reminder_hari'] = filled($data['interval_reminder_hari']) ? (int) $data['interval_reminder_hari'] : null;

        if ($this->editId) {
            $layanan = $this->cariModel($this->editId);
            $this->authorize('update', $layanan);
            $layanan->update($data);
        } else {
            $this->authorize('create', Layanan::class);
            Layanan::create($data);
        }

        $this->selesaiSimpan();
    }

    // ---------------- Harga per cabang ----------------

    /** Cabang yang harganya boleh diatur oleh user ini. */
    protected function cabangUntukHarga()
    {
        $saya = auth()->user();

        return Cabang::query()
            ->when(! $saya->isOwner(), fn ($q) => $q->whereKey($saya->cabang_id))
            ->aktif()
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    public function aturHarga(int $layananId): void
    {
        $layanan = $this->cariModel($layananId);
        $this->authorize('aturHarga', $layanan);

        $this->hargaLayananId = $layanan->id;
        $hargaAda = LayananHarga::query()->where('layanan_id', $layanan->id)->pluck('harga', 'cabang_id');

        $this->harga = [];
        foreach ($this->cabangUntukHarga() as $cabang) {
            $this->harga[$cabang->id] = $hargaAda[$cabang->id] ?? null;
        }

        $this->resetValidation();
        Flux::modal('harga-layanan')->show();
    }

    public function simpanHarga(): void
    {
        $layanan = $this->cariModel($this->hargaLayananId);
        $cabangBoleh = $this->cabangUntukHarga()->pluck('id')->all();

        $this->validate([
            'harga' => ['array'],
            'harga.*' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
        ], attributes: ['harga.*' => 'harga']);

        DB::transaction(function () use ($layanan, $cabangBoleh) {
            foreach ($this->harga as $cabangId => $nilai) {
                $cabangId = (int) $cabangId;

                if (! in_array($cabangId, $cabangBoleh, true)) {
                    continue;
                }

                $this->authorize('aturHarga', [$layanan, $cabangId]);

                if (blank($nilai)) {
                    LayananHarga::query()->where('layanan_id', $layanan->id)->where('cabang_id', $cabangId)->delete();

                    continue;
                }

                LayananHarga::query()->updateOrCreate(
                    ['layanan_id' => $layanan->id, 'cabang_id' => $cabangId],
                    ['harga' => (int) $nilai],
                );
            }
        });

        Flux::modal('harga-layanan')->close();
        Flux::toast(variant: 'success', text: 'Harga tersimpan.');
    }

    public function render()
    {
        $cabangTampil = KonteksCabang::idPembatas();

        $daftar = $this->terapkanFilter(Layanan::query())
            ->when($this->filterJenis !== '', fn ($q) => $q->where('jenis_perangkat_id', $this->filterJenis))
            ->with(['jenisPerangkat:id,nama', 'harga'])
            ->orderBy('jenis_perangkat_id')
            ->orderBy('nama')
            ->paginate(20);

        return view('livewire.master.layanan-index', [
            'daftar' => $daftar,
            'cabangTampil' => $cabangTampil,
            'pilihanJenis' => JenisPerangkat::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
            'pilihanTipe' => TipeLayanan::options(),
            'cabangHarga' => $this->hargaLayananId ? $this->cabangUntukHarga() : collect(),
            'layananHarga' => $this->hargaLayananId ? Layanan::find($this->hargaLayananId) : null,
        ]);
    }
}
