<?php

use App\Http\Controllers\CetakBengkelController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\FilterCabangController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire\Bengkel;
use App\Livewire\Dashboard;
use App\Livewire\Master;
use App\Livewire\Order;
use App\Livewire\Pengaturan;
use App\Livewire\Teknisi;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isTeknisi() ? 'teknisi.beranda' : 'dashboard');
})->name('home');

// ---------- Tugas terjadwal (Vercel Cron, dilindungi CRON_SECRET) ----------
Route::get('cron/bersihkan-upload-sementara', [CronController::class, 'bersihkanUploadSementara'])
    ->middleware('throttle:10,1')
    ->name('cron.bersihkan-upload');
// SEMENTARA: diagnosa penyimpanan di Vercel (dilindungi CRON_SECRET).
Route::get('cron/diagnosa-penyimpanan', [CronController::class, 'diagnosaPenyimpanan'])->middleware('throttle:10,1');

// ---------- Area admin (owner & admin cabang) ----------
Route::middleware(['auth', 'role:owner,admin_cabang'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    Route::post('filter-cabang', FilterCabangController::class)->name('filter-cabang');

    Route::prefix('master')->name('master.')->group(function () {
        Route::get('cabang', Master\CabangIndex::class)->name('cabang');
        Route::get('pengguna', Master\PenggunaIndex::class)->name('pengguna');
        Route::get('tim', Master\TimIndex::class)->name('tim');
        Route::get('jenis-perangkat', Master\JenisPerangkatIndex::class)->name('jenis-perangkat');
        Route::get('layanan', Master\LayananIndex::class)->name('layanan');
        Route::get('pelanggan', Master\PelangganIndex::class)->name('pelanggan');
        Route::get('pelanggan/{pelanggan}', Master\PelangganDetail::class)->name('pelanggan.detail');
        Route::get('barang', Master\BarangIndex::class)->name('barang');
    });

    Route::prefix('order')->name('order.')->group(function () {
        Route::get('/', Order\OrderIndex::class)->name('index');
        Route::get('baru', Order\OrderForm::class)->name('baru');
        Route::get('jadwal', Order\JadwalTim::class)->name('jadwal');
        Route::get('{order}', Order\OrderDetail::class)->name('detail')->whereNumber('order');
        Route::get('{order}/ubah', Order\OrderForm::class)->name('ubah')->whereNumber('order');
    });

    Route::prefix('bengkel')->name('bengkel.')->group(function () {
        Route::get('/', Bengkel\BengkelIndex::class)->name('index');
        Route::get('terima', Bengkel\TerimaBarang::class)->name('terima');
        Route::get('{order}', Bengkel\BengkelDetail::class)->name('detail')->whereNumber('order');
        Route::get('{order}/tanda-terima', [CetakBengkelController::class, 'tandaTerima'])->name('cetak.tanda-terima')->whereNumber('order');
        Route::get('{order}/label', [CetakBengkelController::class, 'label'])->name('cetak.label')->whereNumber('order');
    });

    Route::get('pengaturan', Pengaturan\PengaturanCabang::class)->name('pengaturan');
});

// ---------- Area teknisi (mobile / PWA) ----------
Route::middleware(['auth', 'role:teknisi'])->prefix('teknisi')->name('teknisi.')->group(function () {
    Route::get('/', Teknisi\Beranda::class)->name('beranda');
    Route::get('job/{order}', Teknisi\JobDetail::class)->name('job')->whereNumber('order');
    Route::get('peta', Teknisi\PetaRute::class)->name('peta');
    Route::get('perangkat', Teknisi\Perangkat::class)->name('perangkat');
    Route::post('push', [PushSubscriptionController::class, 'store'])->name('push.simpan');
    Route::delete('push', [PushSubscriptionController::class, 'destroy'])->name('push.hapus');
});

// ---------- Pengaturan akun (semua role) ----------
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
