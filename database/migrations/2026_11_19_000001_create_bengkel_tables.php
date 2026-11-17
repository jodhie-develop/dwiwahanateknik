<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tanda terima barang servis bengkel (1 per order).
        Schema::create('order_penerimaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->unique()->constrained('order_servis')->cascadeOnDelete();
            $table->text('kondisi_fisik')->nullable();
            $table->json('kelengkapan')->nullable(); // daftar teks, mis. ["Remote", "Kabel power"]
            $table->text('catatan_kerusakan')->nullable();
            $table->string('lokasi_simpan', 100)->nullable();
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Estimasi biaya & persetujuan pelanggan. Bisa lebih dari satu (riwayat); yang terakhir yang berlaku.
        Schema::create('order_estimasi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('order_servis')->cascadeOnDelete();
            $table->bigInteger('nominal');
            $table->text('catatan')->nullable(); // hasil diagnosa / rincian
            $table->string('status', 20);
            $table->string('media', 20)->nullable();
            $table->timestamp('diputuskan_at')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'id']);
        });

        Schema::table('order_servis', function (Blueprint $table) {
            $table->timestamp('diambil_at')->nullable()->after('selesai_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_servis', function (Blueprint $table) {
            $table->dropColumn('diambil_at');
        });
        Schema::dropIfExists('order_estimasi');
        Schema::dropIfExists('order_penerimaan');
    }
};
