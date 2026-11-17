<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting key-value. Nilai per cabang menimpa nilai global (cabang_id null).
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = ['cabang_id', 'kunci', 'nilai'];

    public static function ambil(string $kunci, mixed $default = null, ?int $cabangId = null): mixed
    {
        if ($cabangId !== null) {
            $nilai = static::query()->where('cabang_id', $cabangId)->where('kunci', $kunci)->value('nilai');
            if ($nilai !== null) {
                return $nilai;
            }
        }

        return static::query()->whereNull('cabang_id')->where('kunci', $kunci)->value('nilai') ?? $default;
    }

    public static function simpan(string $kunci, mixed $nilai, ?int $cabangId = null): void
    {
        static::query()->updateOrCreate(
            ['cabang_id' => $cabangId, 'kunci' => $kunci],
            ['nilai' => $nilai],
        );
    }
}
