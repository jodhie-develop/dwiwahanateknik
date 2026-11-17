<?php

namespace Database\Factories;

use App\Enums\TipeLayanan;
use App\Models\JenisPerangkat;
use App\Models\Layanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Layanan>
 */
class LayananFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jenis_perangkat_id' => JenisPerangkat::factory(),
            'nama' => 'Servis '.fake()->word(),
            'tipe' => TipeLayanan::Keduanya,
            'masa_garansi_hari' => 30,
            'interval_reminder_hari' => null,
            'aktif' => true,
        ];
    }
}
