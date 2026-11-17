<?php

use App\Support\SupabaseRls;
use Illuminate\Support\Facades\Artisan;

Artisan::command('supabase:rls', function () {
    $tabel = SupabaseRls::aktifkanSemua();

    $this->info($tabel === []
        ? 'Semua tabel sudah memakai RLS (atau koneksi bukan PostgreSQL).'
        : 'RLS diaktifkan pada: '.implode(', ', $tabel));
})->purpose('Aktifkan Row Level Security di semua tabel schema public (Supabase)');
