<?php

namespace Database\Factories;

use App\Models\JenisPerangkat;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitPelanggan>
 */
class UnitPelangganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pelanggan_id' => Pelanggan::factory(),
            'jenis_perangkat_id' => JenisPerangkat::factory(),
            'merk' => fake()->randomElement(['Daikin', 'Panasonic', 'Sharp', 'LG', 'Samsung']),
            'model' => strtoupper(fake()->bothify('??-###')),
            'nomor_seri' => strtoupper(fake()->bothify('SN#######')),
            'ukuran' => fake()->randomElement(['1/2 PK', '1 PK', '1.5 PK', '32 inch']),
            'lokasi' => fake()->randomElement(['Kamar depan', 'Ruang tamu', 'Kamar utama']),
            'alamat' => null,
            'aktif' => true,
        ];
    }
}
