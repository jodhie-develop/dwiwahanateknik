<?php

namespace App\Models;

use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitPelanggan extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'unit_pelanggan';

    protected $fillable = [
        'pelanggan_id', 'jenis_perangkat_id', 'merk', 'model', 'nomor_seri', 'ukuran', 'lokasi', 'alamat',
        'latitude', 'longitude', 'aktif',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /**
     * Koordinat untuk menghitung jarak: koordinat unit jika diisi, jika tidak pakai koordinat pelanggan.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function koordinatEfektif(): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return ['lat' => $this->latitude, 'lng' => $this->longitude];
        }

        $p = $this->pelanggan;

        return $p?->punyaKoordinat() ? ['lat' => $p->latitude, 'lng' => $p->longitude] : null;
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function jenisPerangkat(): BelongsTo
    {
        return $this->belongsTo(JenisPerangkat::class);
    }

    /** Alamat unit jika diisi, jika tidak pakai alamat pelanggan. */
    public function alamatEfektif(): ?string
    {
        return $this->alamat ?: $this->pelanggan?->alamat;
    }
}
