<?php

namespace App\Policies;

use App\Models\Layanan;
use App\Models\User;

class LayananPolicy extends MasterGlobalPolicy
{
    /** Harga per cabang: owner semua cabang, admin cabang hanya cabangnya. */
    public function aturHarga(User $user, Layanan $layanan, ?int $cabangId = null): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->isAdminCabang() && ($cabangId === null || $user->bolehAksesCabang($cabangId));
    }
}
