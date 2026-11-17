<?php

namespace Database\Factories;

use App\Enums\BagianTeknisi;
use App\Models\Cabang;
use App\Models\Teknisi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teknisi>
 */
class TeknisiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cabang_id' => Cabang::factory(),
            'user_id' => null,
            'nama' => fake()->name(),
            'hp' => fake()->numerify('08##########'),
            'tgl_masuk' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
            'bagian' => BagianTeknisi::Lapangan,
            'aktif' => true,
        ];
    }
}
