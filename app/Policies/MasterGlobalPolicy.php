<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\CekPeran;
use Illuminate\Database\Eloquent\Model;

/**
 * Untuk master yang berlaku di semua cabang (jenis perangkat, layanan, barang):
 * admin cabang boleh melihat, hanya owner yang boleh mengubah.
 */
class MasterGlobalPolicy
{
    use CekPeran;

    public function viewAny(User $user): bool
    {
        return $this->staf($user);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isOwner();
    }
}
