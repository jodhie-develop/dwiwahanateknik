<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\Tim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tim>
 */
class TimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cabang_id' => Cabang::factory(),
            'nama_tim' => 'Tim '.fake()->unique()->word(),
            'aktif' => true,
        ];
    }
}
