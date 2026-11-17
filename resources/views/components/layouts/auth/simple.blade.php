<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="mb-2 block overflow-hidden rounded-2xl bg-white p-2 shadow-sm ring-1 ring-black/5" wire:navigate>
                    <img src="/images/logo-dwiwahana.webp" width="800" height="533"
                        alt="Dwiwahana Teknik — Service AC & Elektronik" class="h-auto w-full">
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
