<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counter nomor dokumen per cabang, per awalan, per bulan (YYMM). Dikunci saat dipakai.
        Schema::create('penomoran_dokumen', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('prefix', 10);
            $table->char('periode', 4);
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestamps();

            $table->unique(['cabang_id', 'prefix', 'periode']);
        });

        Schema::create('order_servis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('no_order', 30)->unique();
            $table->string('tipe', 20);
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->restrictOnDelete();
            $table->foreignId('unit_pelanggan_id')->constrained('unit_pelanggan')->restrictOnDelete();
            $table->text('keluhan');
            $table->date('tgl_jadwal');
            $table->time('jam_janji')->nullable(); // info janji ke pelanggan, tidak mengubah antrian
            $table->foreignId('tim_id')->nullable()->constrained('tim')->restrictOnDelete();
            $table->timestamp('ditugaskan_at')->nullable();
            $table->string('status', 30);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('diskon')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('status_bayar', 20)->default('belum');
            $table->foreignId('order_garansi_dari_id')->nullable()->constrained('order_servis')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('checkin_at')->nullable();
            $table->decimal('checkin_lat', 10, 7)->nullable();
            $table->decimal('checkin_lng', 10, 7)->nullable();
            $table->string('tanda_tangan')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'tgl_jadwal', 'status']);
            $table->index(['tim_id', 'tgl_jadwal']);
        });

        // Siapa yang benar-benar mengerjakan (dasar komisi), bukan tim_id.
        Schema::create('order_teknisi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('order_servis')->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('teknisi')->restrictOnDelete();
            $table->string('peran', 20);
            $table->timestamps();

            $table->unique(['order_id', 'teknisi_id']);
        });

        Schema::create('order_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('order_servis')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->foreignId('layanan_id')->nullable()->constrained('layanan')->restrictOnDelete();
            $table->foreignId('barang_id')->nullable()->constrained('barang')->restrictOnDelete();
            $table->string('nama', 150); // salinan nama saat input, agar nota tidak berubah
            $table->decimal('qty', 10, 2);
            $table->bigInteger('harga');
            $table->bigInteger('subtotal');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_foto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('order_servis')->cascadeOnDelete();
            $table->string('kategori', 20);
            $table->string('path');
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_status_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('order_servis')->cascadeOnDelete();
            $table->string('dari_status', 30)->nullable();
            $table->string('ke_status', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_log');
        Schema::dropIfExists('order_foto');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order_teknisi');
        Schema::dropIfExists('order_servis');
        Schema::dropIfExists('penomoran_dokumen');
    }
};
