<?php

namespace App\Policies;

use App\Models\Cabang;
use App\Models\User;

/** Data cabang hanya dikelola owner. */
class CabangPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Cabang $cabang): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Cabang $cabang): bool
    {
        return $user->isOwner();
    }
}
