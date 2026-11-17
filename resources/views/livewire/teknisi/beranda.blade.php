<div class="space-y-5" wire:poll.60s>
    <div>
        <flux:heading size="xl">Halo, {{ $teknisi?->nama ?? auth()->user()->nama }}</flux:heading>
        <flux:text>{{ now()->translatedFormat('l') }}, {{ tgl(now()) }} · {{ $aktif->count() }} job tersisa</flux:text>
    </div>

    @unless ($teknisi)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Data teknisi Anda belum lengkap">
            Minta admin membuka akun Anda di menu Pengguna & Teknisi lalu menyimpannya.
        </flux:callout>
    @endunless

    <x-teknisi.tombol-notifikasi />

    @if ($aktif->isNotEmpty() || $selesai->isNotEmpty())
        <flux:button icon="map" :href="route('teknisi.peta')" wire:navigate class="h-12 w-full">Lihat Peta</flux:button>
    @endif

    @forelse ($aktif as $o)
        @php($k = $o->koordinat())
        <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800" wire:key="job-{{ $o->id }}">
            <a href="{{ route('teknisi.job', $o) }}" wire:navigate class="block space-y-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="text-lg font-semibold">{{ $loop->iteration }}. {{ $o->pelanggan?->nama }}</div>
                    <flux:badge :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $o->unit?->alamatEfektif() }}</div>
                <div class="text-sm">
                    <span class="font-medium">{{ $o->unit?->jenisPerangkat?->nama }} {{ $o->unit?->merk }}</span>
                    {{ $o->unit?->lokasi ? '· '.$o->unit->lokasi : '' }}
                </div>
                <div class="line-clamp-2 text-sm text-zinc-500">{{ $o->keluhan }}</div>
                @if ($o->jam_janji)
                    <div class="text-sm font-medium text-amber-600">Janji jam {{ substr($o->jam_janji, 0, 5) }}</div>
                @endif
            </a>
            <div class="grid grid-cols-2 gap-2">
                @if ($k)
                    <flux:button icon="map" :href="'https://www.google.com/maps/dir/?api=1&destination='.$k['lat'].','.$k['lng']" target="_blank" class="w-full">Navigasi</flux:button>
                @else
                    <flux:button icon="map" disabled class="w-full">Lokasi kosong</flux:button>
                @endif
                <flux:button variant="primary" icon:trailing="arrow-right" :href="route('teknisi.job', $o)" wire:navigate class="w-full">Buka</flux:button>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-600">
            Belum ada job untuk hari ini.
        </div>
    @endforelse

    @if ($bengkel->isNotEmpty())
        <div class="space-y-2">
            <flux:heading>Barang di bengkel ({{ $bengkel->count() }})</flux:heading>
            @foreach ($bengkel as $o)
                <a href="{{ route('teknisi.job', $o) }}" wire:navigate wire:key="bkl-{{ $o->id }}"
                   class="block space-y-1 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-medium">{{ $o->unit?->jenisPerangkat?->nama }} {{ $o->unit?->merk }} · {{ $o->pelanggan?->nama }}</div>
                        <flux:badge size="sm" :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
                    </div>
                    <div class="font-mono text-xs text-zinc-500">{{ $o->no_order }} · rak {{ $o->penerimaan?->lokasi_simpan ?: '-' }} · masuk {{ tgl($o->created_at) }}</div>
                    <div class="line-clamp-1 text-sm text-zinc-500">{{ $o->keluhan }}</div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($selesai->isNotEmpty())
        <div class="space-y-2">
            <flux:heading>Selesai / batal hari ini</flux:heading>
            @foreach ($selesai as $o)
                <a href="{{ route('teknisi.job', $o) }}" wire:navigate wire:key="done-{{ $o->id }}"
                   class="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <span>{{ $o->pelanggan?->nama }}</span>
                    <flux:badge size="sm" :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
                </a>
            @endforeach
        </div>
    @endif
</div>
