<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Titik kantor cabang: titik awal rute teknisi sebelum job pertama. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabang', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('telp');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('cabang', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
