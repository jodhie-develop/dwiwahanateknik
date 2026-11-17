<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('BRG-####')),
            'nama' => ucfirst(fake()->words(2, true)),
            'satuan' => fake()->randomElement(['pcs', 'meter', 'kg', 'unit']),
            'harga_jual' => fake()->numberBetween(5, 500) * 1000,
            'aktif' => true,
        ];
    }
}
