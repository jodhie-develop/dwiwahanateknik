{{-- Kartu ringkas satu order di halaman Jadwal Tim. --}}
<a href="{{ route('order.detail', $o) }}" wire:navigate wire:key="kartu-{{ $o->id }}"
   class="block rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40 {{ $o->status === App\Enums\StatusOrder::Batal ? 'opacity-50' : '' }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="font-medium">
                @isset($nomor)<span class="text-zinc-400">{{ $nomor }}.</span>@endisset
                {{ $o->pelanggan?->nama }}
            </div>
            <div class="truncate text-xs text-zinc-500">{{ $o->pelanggan?->alamat }}</div>
            <div class="text-xs text-zinc-500">
                {{ $o->unit?->jenisPerangkat?->nama }} · input {{ $o->created_at->format('H:i') }}
                @if ($o->jam_janji) · <span class="font-medium text-amber-600">janji {{ substr($o->jam_janji, 0, 5) }}</span>@endif
                @unless ($o->koordinat()) · <span class="text-amber-600">tanpa lokasi</span>@endunless
            </div>
        </div>
        <flux:badge size="sm" :color="$o->status->warna()" inset="top bottom">{{ $o->status->label() }}</flux:badge>
    </div>
</a>
