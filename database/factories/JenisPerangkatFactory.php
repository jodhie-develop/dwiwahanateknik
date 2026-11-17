<?php

namespace Database\Factories;

use App\Models\JenisPerangkat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisPerangkat>
 */
class JenisPerangkatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => ucfirst(fake()->unique()->word()),
            'aktif' => true,
        ];
    }
}
