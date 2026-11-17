<?php

namespace App\Models;

use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisPerangkat extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'jenis_perangkat';

    protected $fillable = ['nama', 'aktif'];

    public function layanan(): HasMany
    {
        return $this->hasMany(Layanan::class);
    }
}
