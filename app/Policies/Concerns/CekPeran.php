<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait CekPeran
{
    protected function staf(User $user): bool
    {
        return $user->isOwner() || $user->isAdminCabang();
    }

    protected function stafDiCabang(User $user, ?int $cabangId): bool
    {
        return $this->staf($user) && $user->bolehAksesCabang($cabangId);
    }
}
