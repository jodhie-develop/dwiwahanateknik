@props(['user'])

<flux:menu class="w-[220px]">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
            {{ $user->initials() }}
        </span>
        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-semibold">{{ $user->nama }}</span>
            <span class="truncate text-xs">{{ $user->role->label() }} · {{ $user->username }}</span>
        </div>
    </div>

    <flux:menu.separator />

    @if ($user->isTeknisi())
        <flux:menu.item :href="route('teknisi.perangkat')" icon="device-phone-mobile" wire:navigate>Aplikasi &amp; Notifikasi</flux:menu.item>
    @endif
    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Pengaturan akun</flux:menu.item>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Keluar</flux:menu.item>
    </form>
</flux:menu>
