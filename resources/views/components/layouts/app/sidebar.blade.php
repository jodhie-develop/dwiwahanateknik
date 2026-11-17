@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('home') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            @if ($user->isOwner())
                <x-filter-cabang />
            @elseif ($user->cabang)
                <div class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <div class="text-xs text-zinc-500">Cabang</div>
                    <div class="font-medium">{{ $user->cabang->nama }}</div>
                </div>
            @endif

            <flux:navlist variant="outline">
                @if ($user->isTeknisi())
                    <flux:navlist.item icon="home" :href="route('teknisi.beranda')" wire:navigate>Job Hari Ini</flux:navlist.item>
                @else
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Dashboard</flux:navlist.item>
                @endif

                @can('viewAny', App\Models\OrderServis::class)
                    <flux:navlist.group heading="Operasional" class="grid">
                        <flux:navlist.item icon="clipboard-document-check" :href="route('order.index')" :current="request()->routeIs('order.index', 'order.baru', 'order.detail', 'order.ubah')" wire:navigate>Order Kunjungan</flux:navlist.item>
                        <flux:navlist.item icon="calendar-days" :href="route('order.jadwal')" :current="request()->routeIs('order.jadwal')" wire:navigate>Jadwal Tim</flux:navlist.item>
                        <flux:navlist.item icon="wrench" :href="route('bengkel.index')" :current="request()->routeIs('bengkel.*')" wire:navigate>Order Bengkel</flux:navlist.item>
                    </flux:navlist.group>
                @endcan

                @can('akses-admin')
                    <flux:navlist.group heading="Data Master" class="grid">
                        @can('viewAny', App\Models\Pelanggan::class)
                            <flux:navlist.item icon="users" :href="route('master.pelanggan')" :current="request()->routeIs('master.pelanggan*')" wire:navigate>Pelanggan</flux:navlist.item>
                        @endcan
                        @can('viewAny', App\Models\User::class)
                            <flux:navlist.item icon="identification" :href="route('master.pengguna')" :current="request()->routeIs('master.pengguna')" wire:navigate>Pengguna & Teknisi</flux:navlist.item>
                        @endcan
                        @can('viewAny', App\Models\Tim::class)
                            <flux:navlist.item icon="user-group" :href="route('master.tim')" :current="request()->routeIs('master.tim')" wire:navigate>Tim</flux:navlist.item>
                        @endcan
                        @can('viewAny', App\Models\Layanan::class)
                            <flux:navlist.item icon="clipboard-document-list" :href="route('master.layanan')" :current="request()->routeIs('master.layanan')" wire:navigate>Layanan & Harga</flux:navlist.item>
                        @endcan
                        @can('viewAny', App\Models\Barang::class)
                            <flux:navlist.item icon="cube" :href="route('master.barang')" :current="request()->routeIs('master.barang')" wire:navigate>Barang</flux:navlist.item>
                        @endcan
                        @can('viewAny', App\Models\JenisPerangkat::class)
                            <flux:navlist.item icon="tv" :href="route('master.jenis-perangkat')" :current="request()->routeIs('master.jenis-perangkat')" wire:navigate>Jenis Perangkat</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcan

                @can('akses-admin')
                    <flux:navlist.group heading="Sistem" class="grid">
                        <flux:navlist.item icon="adjustments-horizontal" :href="route('pengaturan')" :current="request()->routeIs('pengaturan')" wire:navigate>Pengaturan</flux:navlist.item>
                        @can('viewAny', App\Models\Cabang::class)
                            <flux:navlist.item icon="building-storefront" :href="route('master.cabang')" :current="request()->routeIs('master.cabang')" wire:navigate>Cabang</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcan
            </flux:navlist>

            <flux:spacer />

            <!-- Menu user (desktop) -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile :name="$user->nama" :initials="$user->initials()" icon-trailing="chevrons-up-down" />

                <x-menu-user :user="$user" />
            </flux:dropdown>
        </flux:sidebar>

        <!-- Menu user (mobile) -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />

                <x-menu-user :user="$user" />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <flux:toast />

        @fluxScripts
    </body>
</html>
