<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Menentukan cabang yang sedang "aktif" untuk user yang login.
 *
 * - admin_cabang & teknisi: selalu terkunci ke cabang miliknya.
 * - owner: semua cabang, kecuali sedang memilih filter cabang (disimpan di session).
 */
class KonteksCabang
{
    public const SESSION_KEY = 'filter_cabang_id';

    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Cabang yang membatasi query saat ini, atau null jika tidak dibatasi (owner tanpa filter / tidak ada login).
     * Mengembalikan 0 untuk user non-owner yang tidak punya cabang, supaya query tidak menghasilkan apa pun.
     */
    public static function idPembatas(): ?int
    {
        $user = static::user();

        if (! $user) {
            return null;
        }

        if (! $user->isOwner()) {
            return (int) ($user->cabang_id ?? 0);
        }

        return static::filterOwner();
    }

    public static function filterOwner(): ?int
    {
        if (! request()->hasSession()) {
            return null;
        }

        $id = request()->session()->get(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    public static function setFilterOwner(?int $cabangId): void
    {
        if ($cabangId) {
            request()->session()->put(self::SESSION_KEY, $cabangId);
        } else {
            request()->session()->forget(self::SESSION_KEY);
        }
    }
}
