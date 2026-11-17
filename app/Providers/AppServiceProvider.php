<?php

namespace App\Providers;

use App\Models\Barang;
use App\Models\JenisPerangkat;
use App\Models\Layanan;
use App\Models\Pelanggan;
use App\Models\UnitPelanggan;
use App\Models\User;
use App\Policies\LayananPolicy;
use App\Policies\MasterGlobalPolicy;
use App\Policies\PelangganPolicy;
use App\Support\SupabaseRls;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        // Tangkap lazy loading / atribut salah ketik saat development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Area admin: owner & admin cabang.
        Gate::define('akses-admin', fn (User $user) => $user->isOwner() || $user->isAdminCabang());

        // Policy yang namanya tidak mengikuti konvensi model.
        Gate::policy(JenisPerangkat::class, MasterGlobalPolicy::class);
        Gate::policy(Barang::class, MasterGlobalPolicy::class);
        Gate::policy(Layanan::class, LayananPolicy::class);
        Gate::policy(Pelanggan::class, PelangganPolicy::class);
        Gate::policy(UnitPelanggan::class, PelangganPolicy::class);

        // Setiap selesai migrate di PostgreSQL (Supabase), pastikan semua tabel memakai RLS.
        Event::listen(MigrationsEnded::class, fn () => SupabaseRls::aktifkanSemua());
    }
}
