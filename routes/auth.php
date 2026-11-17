<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Tidak ada registrasi publik: akun dibuat oleh owner/admin cabang.
Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')->name('login');
});

Route::post('logout', Logout::class)->name('logout');
