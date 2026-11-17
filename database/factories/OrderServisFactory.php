<?php

namespace Database\Factories;

use App\Enums\StatusBayar;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\UnitPelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderServis>
 */
class OrderServisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cabang_id' => Cabang::factory(),
            'no_order' => 'KJG/TST/'.fake()->unique()->numerify('####/####'),
            'tipe' => TipeOrder::Kunjungan,
            'unit_pelanggan_id' => UnitPelanggan::factory(),
            'pelanggan_id' => fn (array $a) => UnitPelanggan::query()->find($a['unit_pelanggan_id'])->pelanggan_id,
            'keluhan' => 'AC tidak dingin',
            'tgl_jadwal' => today()->toDateString(),
            'status' => StatusOrder::Dijadwalkan,
            'status_bayar' => StatusBayar::Belum,
            'dibuat_oleh' => User::factory()->owner(),
        ];
    }

    /** Lokasi unit (koordinat di unit). */
    public function diLokasi(float $lat, float $lng): static
    {
        return $this->state(fn () => [
            'unit_pelanggan_id' => UnitPelanggan::factory()->state(['latitude' => $lat, 'longitude' => $lng]),
        ]);
    }
}
