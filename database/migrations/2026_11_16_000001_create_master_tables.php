<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknisi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nama', 100);
            $table->string('hp', 30)->nullable();
            $table->string('foto')->nullable();
            $table->date('tgl_masuk')->nullable();
            $table->string('bagian', 20);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tim', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nama_tim', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tim_anggota', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tim_id')->constrained('tim')->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('teknisi')->restrictOnDelete();
            $table->string('peran', 20);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai')->nullable();
            $table->timestamps();

            $table->index(['tim_id', 'tgl_selesai']);
        });

        Schema::create('jenis_perangkat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama', 100)->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('layanan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('jenis_perangkat_id')->constrained('jenis_perangkat')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('tipe', 20);
            $table->unsignedInteger('masa_garansi_hari')->default(0);
            $table->unsignedInteger('interval_reminder_hari')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('layanan_harga', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('layanan_id')->constrained('layanan')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->bigInteger('harga');
            $table->timestamps();

            $table->unique(['layanan_id', 'cabang_id']);
        });

        Schema::create('pelanggan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama', 150);
            $table->string('hp', 30)->nullable()->index();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_pelanggan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('jenis_perangkat_id')->constrained('jenis_perangkat')->restrictOnDelete();
            $table->string('merk', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('nomor_seri', 100)->nullable();
            $table->string('ukuran', 50)->nullable();
            $table->string('lokasi', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('satuan', 20);
            $table->bigInteger('harga_jual')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Setting aplikasi. cabang_id null = berlaku global.
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->cascadeOnDelete();
            $table->string('kunci', 100);
            $table->text('nilai')->nullable();
            $table->timestamps();

            // nullsNotDistinct: di PostgreSQL, setting global (cabang_id null) juga harus unik per kunci.
            $table->unique(['cabang_id', 'kunci'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('barang');
        Schema::dropIfExists('unit_pelanggan');
        Schema::dropIfExists('pelanggan');
        Schema::dropIfExists('layanan_harga');
        Schema::dropIfExists('layanan');
        Schema::dropIfExists('jenis_perangkat');
        Schema::dropIfExists('tim_anggota');
        Schema::dropIfExists('tim');
        Schema::dropIfExists('teknisi');
    }
};
