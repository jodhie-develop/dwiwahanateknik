<?php

namespace App\Models;

use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'cabang';

    protected $fillable = ['kode', 'nama', 'alamat', 'telp', 'latitude', 'longitude', 'aktif'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return array{lat: float, lng: float}|null */
    public function koordinat(): ?array
    {
        return $this->latitude !== null && $this->longitude !== null
            ? ['lat' => $this->latitude, 'lng' => $this->longitude]
            : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teknisi(): HasMany
    {
        return $this->hasMany(Teknisi::class);
    }
}
