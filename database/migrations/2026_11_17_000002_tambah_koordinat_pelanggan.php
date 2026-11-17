<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koordinat lokasi untuk penjadwalan tim berdasarkan jarak.
 * Unit tanpa koordinat memakai koordinat pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pelanggan', 'unit_pelanggan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('alamat');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }
    }

    public function down(): void
    {
        foreach (['pelanggan', 'unit_pelanggan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }
    }
};
