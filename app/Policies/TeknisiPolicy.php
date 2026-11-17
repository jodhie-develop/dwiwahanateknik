<?php

namespace App\Policies;

use App\Models\Teknisi;
use App\Models\User;
use App\Policies\Concerns\CekPeran;

class TeknisiPolicy
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

    public function update(User $user, Teknisi $teknisi): bool
    {
        return $this->stafDiCabang($user, $teknisi->cabang_id);
    }

    public function delete(User $user, Teknisi $teknisi): bool
    {
        return $this->stafDiCabang($user, $teknisi->cabang_id);
    }
}
