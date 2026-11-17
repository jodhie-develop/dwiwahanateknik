<?php

namespace App\Models;

use App\Enums\TipeLayanan;
use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Layanan extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'layanan';

    protected $fillable = [
        'jenis_perangkat_id', 'nama', 'tipe', 'masa_garansi_hari', 'interval_reminder_hari', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeLayanan::class,
            'masa_garansi_hari' => 'integer',
            'interval_reminder_hari' => 'integer',
        ];
    }

    public function jenisPerangkat(): BelongsTo
    {
        return $this->belongsTo(JenisPerangkat::class);
    }

    /** Harga per cabang (ikut dibatasi CabangScope). */
    public function harga(): HasMany
    {
        return $this->hasMany(LayananHarga::class);
    }

    public function hargaDiCabang(int $cabangId): ?int
    {
        return $this->harga()->withoutGlobalScopes()->where('cabang_id', $cabangId)->value('harga');
    }
}
