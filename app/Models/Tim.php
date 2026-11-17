<?php

namespace App\Models;

use App\Models\Concerns\BerCabang;
use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tim extends Model
{
    use BerCabang, HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'tim';

    protected $fillable = ['cabang_id', 'nama_tim', 'aktif'];

    public function anggota(): HasMany
    {
        return $this->hasMany(TimAnggota::class);
    }

    /** Anggota yang masih aktif per hari ini (lihat TimAnggota::scopeBerlaku). */
    public function anggotaAktif(): HasMany
    {
        return $this->anggota()->berlaku();
    }
}
