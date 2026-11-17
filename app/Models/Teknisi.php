<?php

namespace App\Models;

use App\Enums\BagianTeknisi;
use App\Models\Concerns\BerCabang;
use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Profil teknisi. Selalu 1:1 dengan user ber-role teknisi dan dikelola dari menu Pengguna.
 * nama, cabang_id, aktif, dan status hapus disinkronkan dari user (lihat User::booted).
 */
class Teknisi extends Model
{
    use BerCabang, HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'teknisi';

    protected $fillable = ['cabang_id', 'user_id', 'nama', 'hp', 'foto', 'tgl_masuk', 'bagian', 'aktif'];

    protected static function booted(): void
    {
        // Teknisi yang dihapus otomatis keluar dari tim (riwayat keanggotaan tetap tersimpan).
        static::deleted(function (Teknisi $teknisi) {
            $teknisi->keanggotaanTim()->berlaku()->update(['tgl_selesai' => now()->toDateString()]);
        });
    }

    protected function casts(): array
    {
        return [
            'tgl_masuk' => 'date',
            'bagian' => BagianTeknisi::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keanggotaanTim(): HasMany
    {
        return $this->hasMany(TimAnggota::class);
    }
}
