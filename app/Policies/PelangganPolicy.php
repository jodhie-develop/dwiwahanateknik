<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\CekPeran;
use Illuminate\Database\Eloquent\Model;

/** Pelanggan & unit lintas cabang: semua staf (owner/admin cabang) boleh mengelola. */
class PelangganPolicy
{
    use CekPeran;

    public function viewAny(User $user): bool
    {
        return $this->staf($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->staf($user);
    }

    public function create(User $user): bool
    {
        return $this->staf($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->staf($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->staf($user);
    }
}
