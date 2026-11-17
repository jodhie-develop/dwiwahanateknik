<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'hp' => fake()->numerify('08##########'),
            'alamat' => fake()->address(),
            'catatan' => null,
            'aktif' => true,
        ];
    }
}
