<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\PunyaStatusAktif;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable, PunyaStatusAktif, SoftDeletes;

    protected $fillable = [
        'nama',
        'username',
        'email',
        'password',
        'role',
        'cabang_id',
        'aktif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        // Jaga data teknisi selalu sejalan dengan akunnya.
        static::saved(function (User $user) {
            if (! $user->wasChanged(['nama', 'cabang_id', 'aktif'])) {
                return;
            }

            $user->teknisi()->withoutGlobalScopes()->get()->each(fn (Teknisi $t) => $t->forceFill([
                'nama' => $user->nama,
                'cabang_id' => $user->cabang_id,
                'aktif' => $user->aktif,
            ])->saveQuietly());
        });

        static::deleted(function (User $user) {
            $user->teknisi()->withoutGlobalScopes()->get()->each->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function teknisi(): HasOne
    {
        return $this->hasOne(Teknisi::class);
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isAdminCabang(): bool
    {
        return $this->role === Role::AdminCabang;
    }

    public function isTeknisi(): bool
    {
        return $this->role === Role::Teknisi;
    }

    /** Owner boleh semua cabang; selain itu hanya cabangnya sendiri. */
    public function bolehAksesCabang(?int $cabangId): bool
    {
        return $this->isOwner() || ($this->cabang_id !== null && (int) $this->cabang_id === (int) $cabangId);
    }

    /** Batasi daftar user sesuai hak akses (user tidak memakai global scope agar auth tidak rekursif). */
    public function scopeTerlihatOleh(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->where('cabang_id', $user->cabang_id ?? 0);
    }

    public function initials(): string
    {
        return Str::of($this->nama)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $kata) => Str::of($kata)->substr(0, 1)->upper())
            ->implode('');
    }
}
