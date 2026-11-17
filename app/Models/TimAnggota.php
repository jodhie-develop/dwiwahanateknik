<?php

namespace App\Models;

use App\Enums\PeranTim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimAnggota extends Model
{
    protected $table = 'tim_anggota';

    protected $fillable = ['tim_id', 'teknisi_id', 'peran', 'tgl_mulai', 'tgl_selesai'];

    protected function casts(): array
    {
        return [
            'peran' => PeranTim::class,
            'tgl_mulai' => 'date',
            'tgl_selesai' => 'date',
        ];
    }

    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(Teknisi::class);
    }

    /**
     * Anggota yang berlaku per hari ini.
     * tgl_selesai = tanggal berhenti: mulai tanggal itu teknisi sudah tidak dihitung sebagai anggota.
     */
    public function scopeBerlaku(Builder $query): Builder
    {
        $hariIni = now()->toDateString();

        return $query->where(fn (Builder $q) => $q
            ->whereNull('tgl_selesai')
            ->orWhereDate('tgl_selesai', '>', $hariIni));
    }
}
