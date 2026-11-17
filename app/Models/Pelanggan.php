<?php

namespace App\Models;

use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pelanggan bersifat lintas cabang, jadi tidak memakai BerCabang. */
class Pelanggan extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'pelanggan';

    protected $fillable = ['nama', 'hp', 'alamat', 'latitude', 'longitude', 'catatan', 'aktif'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    public function punyaKoordinat(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function unit(): HasMany
    {
        return $this->hasMany(UnitPelanggan::class);
    }
}
