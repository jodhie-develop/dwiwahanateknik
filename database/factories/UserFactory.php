<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Cabang;
use App\Models\Teknisi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => Str::lower(fake()->unique()->bothify('user.####??')),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::AdminCabang,
            'cabang_id' => Cabang::factory(),
            'aktif' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner, 'cabang_id' => null]);
    }

    public function adminCabang(?Cabang $cabang = null): static
    {
        return $this->state(fn () => array_filter([
            'role' => Role::AdminCabang,
            'cabang_id' => $cabang?->id,
        ]));
    }

    /** User teknisi selalu dibuat beserta profil teknisinya. */
    public function teknisi(?Cabang $cabang = null, array $profil = []): static
    {
        return $this->state(fn () => array_filter([
            'role' => Role::Teknisi,
            'cabang_id' => $cabang?->id,
        ]))->afterCreating(fn (User $user) => Teknisi::factory()->create([
            'user_id' => $user->id,
            'cabang_id' => $user->cabang_id,
            'nama' => $user->nama,
            ...$profil,
        ]));
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
