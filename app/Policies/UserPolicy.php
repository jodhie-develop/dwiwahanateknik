<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Policies\Concerns\CekPeran;

/**
 * Owner mengelola semua user.
 * Admin cabang hanya mengelola akun teknisi di cabangnya sendiri.
 */
class UserPolicy
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

    public function update(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->isAdminCabang()
            && $model->role === Role::Teknisi
            && $user->bolehAksesCabang($model->cabang_id);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $this->update($user, $model);
    }

    /** @return list<Role> role yang boleh diberikan oleh $user */
    public static function roleYangBolehDiberikan(User $user): array
    {
        return $user->isOwner() ? Role::cases() : [Role::Teknisi];
    }
}
