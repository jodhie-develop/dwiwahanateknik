<?php

namespace App\Livewire\Concerns;

use App\Models\JenisPerangkat;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pilih pelanggan & unit di form order (cari pelanggan / pelanggan baru, pilih unit / unit baru).
 * Dipakai form order kunjungan dan bengkel. Butuh trait IsiKoordinat (lokasi pelanggan baru).
 */
trait PilihPelangganUnit
{
    public string $cariPelanggan = '';

    public ?int $pelanggan_id = null;

    public bool $pelangganBaru = false;

    public string $pb_nama = '';

    public string $pb_hp = '';

    public string $pb_alamat = '';

    public ?int $unit_pelanggan_id = null;

    public bool $unitBaru = false;

    public ?int $ub_jenis_perangkat_id = null;

    public string $ub_merk = '';

    public string $ub_model = '';

    public string $ub_ukuran = '';

    public string $ub_lokasi = '';

    public string $ub_nomor_seri = '';

    public function pilihPelanggan(int $id): void
    {
        $pelanggan = Pelanggan::query()->aktif()->findOrFail($id);
        $this->pelanggan_id = $pelanggan->id;
        $this->pelangganBaru = false;
        $this->cariPelanggan = '';

        $unit = $pelanggan->unit()->aktif()->pluck('id');
        $this->unit_pelanggan_id = $unit->count() === 1 ? $unit->first() : null;
        $this->unitBaru = $unit->isEmpty();
    }

    public function gantiPelanggan(): void
    {
        $this->reset('pelanggan_id', 'unit_pelanggan_id', 'unitBaru', 'pelangganBaru');
    }

    public function mulaiPelangganBaru(): void
    {
        $this->reset('pelanggan_id', 'unit_pelanggan_id', 'pb_nama', 'pb_hp', 'pb_alamat', 'linkMaps', 'latitude', 'longitude');
        $this->pb_nama = $this->cariPelanggan;
        $this->pelangganBaru = true;
        $this->unitBaru = true;
    }

    /** Aturan validasi pelanggan & unit (digabung ke validate() komponen). */
    protected function aturanPelangganUnit(bool $alamatWajib = true): array
    {
        return [
            'pelanggan_id' => [Rule::requiredIf(! $this->pelangganBaru), 'nullable', 'integer', Rule::exists('pelanggan', 'id')->whereNull('deleted_at')],
            'pb_nama' => [Rule::requiredIf($this->pelangganBaru), 'nullable', 'string', 'max:150'],
            'pb_hp' => ['nullable', 'string', 'max:30', 'regex:/^(\+?62|0)8[0-9]{7,13}$/'],
            'pb_alamat' => [Rule::requiredIf($this->pelangganBaru && $alamatWajib), 'nullable', 'string', 'max:1000'],
            ...$this->aturanKoordinat(),
            'unit_pelanggan_id' => [Rule::requiredIf(! $this->unitBaru), 'nullable', 'integer'],
            'ub_jenis_perangkat_id' => [Rule::requiredIf($this->unitBaru), 'nullable', 'integer', Rule::exists('jenis_perangkat', 'id')->whereNull('deleted_at')],
            'ub_merk' => ['nullable', 'string', 'max:100'],
            'ub_model' => ['nullable', 'string', 'max:100'],
            'ub_ukuran' => ['nullable', 'string', 'max:50'],
            'ub_lokasi' => ['nullable', 'string', 'max:100'],
            'ub_nomor_seri' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function pesanPelangganUnit(): array
    {
        return [
            'pb_hp.regex' => 'Nomor HP/WA tidak valid. Contoh: 081234567890.',
            'unit_pelanggan_id.required' => 'Pilih unit / barang, atau tambah unit baru.',
            'pelanggan_id.required' => 'Pilih pelanggan, atau tambah pelanggan baru.',
        ];
    }

    protected function atributPelangganUnit(): array
    {
        return [
            'pb_nama' => 'nama pelanggan', 'pb_hp' => 'nomor HP', 'pb_alamat' => 'alamat',
            'ub_jenis_perangkat_id' => 'jenis perangkat', 'ub_nomor_seri' => 'nomor seri',
        ];
    }

    /**
     * Buat pelanggan/unit baru bila perlu (panggil di dalam transaksi setelah validate()).
     *
     * @param  array<string, mixed>  $data  hasil validate()
     * @return array{0: int, 1: int} [pelanggan_id, unit_pelanggan_id]
     */
    protected function simpanPelangganUnit(array $data): array
    {
        if (! $this->unitBaru && ! $this->pelangganBaru) {
            $cocok = UnitPelanggan::query()->whereKey($data['unit_pelanggan_id'])->where('pelanggan_id', $data['pelanggan_id'])->exists();
            if (! $cocok) {
                throw ValidationException::withMessages(['unit_pelanggan_id' => 'Unit tidak sesuai dengan pelanggan.']);
            }
        }

        $pelangganId = $data['pelanggan_id'];

        if ($this->pelangganBaru) {
            $this->authorize('create', Pelanggan::class);
            $pelangganId = Pelanggan::create([
                'nama' => $data['pb_nama'],
                'hp' => $data['pb_hp'] ?: null,
                'alamat' => $data['pb_alamat'] ?: null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ])->id;
        }

        $unitId = $data['unit_pelanggan_id'];

        if ($this->unitBaru) {
            $unitId = UnitPelanggan::create([
                'pelanggan_id' => $pelangganId,
                'jenis_perangkat_id' => $data['ub_jenis_perangkat_id'],
                'merk' => $data['ub_merk'] ?: null,
                'model' => $data['ub_model'] ?: null,
                'ukuran' => $data['ub_ukuran'] ?: null,
                'lokasi' => $data['ub_lokasi'] ?: null,
                'nomor_seri' => $data['ub_nomor_seri'] ?: null,
            ])->id;
        }

        return [$pelangganId, $unitId];
    }

    /** Data untuk partial livewire.order.partials.pilih-pelanggan-unit. */
    protected function dataPelangganUnit(): array
    {
        $hasilCari = collect();
        if (! $this->pelanggan_id && ! $this->pelangganBaru && mb_strlen(trim($this->cariPelanggan)) >= 2) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $q = '%'.trim($this->cariPelanggan).'%';
            $hasilCari = Pelanggan::query()->aktif()
                ->where(fn ($w) => $w->where('nama', $like, $q)->orWhere('hp', $like, $q)->orWhere('alamat', $like, $q))
                ->orderBy('nama')->limit(8)->get(['id', 'nama', 'hp', 'alamat']);
        }

        $pelanggan = $this->pelanggan_id ? Pelanggan::query()->find($this->pelanggan_id) : null;

        /** @var Collection $daftarUnit */
        $daftarUnit = $pelanggan
            ? $pelanggan->unit()->aktif()->with('jenisPerangkat:id,nama')->orderBy('id')->get()
            : collect();

        return [
            'hasilCari' => $hasilCari,
            'pelanggan' => $pelanggan,
            'daftarUnit' => $daftarUnit,
            'pilihanJenis' => JenisPerangkat::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
            'jenisUnitTerpilih' => $this->unitBaru
                ? $this->ub_jenis_perangkat_id
                : $daftarUnit->firstWhere('id', $this->unit_pelanggan_id)?->jenis_perangkat_id,
        ];
    }
}
