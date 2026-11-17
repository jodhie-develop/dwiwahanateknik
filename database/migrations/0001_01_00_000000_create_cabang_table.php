<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dibuat sebelum tabel users karena users.cabang_id merujuk ke sini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode', 10)->unique();
            $table->string('nama', 100);
            $table->text('alamat')->nullable();
            $table->string('telp', 30)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabang');
    }
};
