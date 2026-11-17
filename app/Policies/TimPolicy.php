<?php

namespace App\Policies;

use App\Models\Tim;
use App\Models\User;
use App\Policies\Concerns\CekPeran;

class TimPolicy
{
    use CekPeran;

    public function viewAny(User $user): bool
    {
        return $this->staf($user);
    }

    public function create(User $user): bool
    {
        return $this->staf($user);
    }

    public function update(User $user, Tim $tim): bool
    {
        return $this->stafDiCabang($user, $tim->cabang_id);
    }

    public function delete(User $user, Tim $tim): bool
    {
        return $this->stafDiCabang($user, $tim->cabang_id);
    }
}
