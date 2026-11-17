{{-- Layout mobile-first untuk teknisi lapangan: header ringkas, konten satu kolom, tombol besar. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
            }
        </script>
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-900">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-zinc-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90">
            <a href="{{ route('teknisi.beranda') }}" class="flex items-center gap-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:dropdown position="bottom" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <x-menu-user :user="auth()->user()" />
            </flux:dropdown>
        </header>

        <main class="mx-auto w-full max-w-xl px-4 py-5">
            {{ $slot }}
        </main>

        <flux:toast />

        @fluxScripts
    </body>
</html>
