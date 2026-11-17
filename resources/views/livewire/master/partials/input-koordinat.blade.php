{{-- Isian koordinat dari link Google Maps. Butuh trait App\Livewire\Concerns\IsiKoordinat.
     Pemakaian: @include('livewire.master.partials.input-koordinat', ['keterangan' => '...']) --}}
@php($keterangan ??= null)

<div class="space-y-2">
    <flux:input
        wire:model.live.blur="linkMaps"
        label="Lokasi (link Google Maps)"
        placeholder="Tempel link share lokasi, mis. https://maps.app.goo.gl/..."
        icon="map-pin"
        :description="$keterangan"
    />
    <div wire:loading wire:target="linkMaps" class="text-sm text-zinc-500">Membaca lokasi...</div>

    @if ($latitude !== null && $longitude !== null)
        <div class="flex flex-wrap items-center gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-700/50">
            <flux:icon.map-pin variant="micro" class="text-green-600" />
            <span class="font-mono">{{ $latitude }}, {{ $longitude }}</span>
            <flux:spacer />
            <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square"
                :href="App\Support\KoordinatMaps::urlMaps($latitude, $longitude)" target="_blank">Lihat di Maps</flux:button>
            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="hapusKoordinat">Hapus</flux:button>
        </div>
    @endif
</div>
