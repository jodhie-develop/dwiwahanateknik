<?php

namespace Database\Factories;

use App\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cabang>
 */
class CabangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('???')),
            'nama' => fake()->city(),
            'alamat' => fake()->address(),
            'telp' => fake()->phoneNumber(),
            'aktif' => true,
        ];
    }
}
