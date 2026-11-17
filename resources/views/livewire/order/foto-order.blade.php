<div class="space-y-4">
    @if ($bolehUbah)
        <div class="space-y-3">
            <flux:radio.group wire:model.live="kategori" variant="segmented" size="sm">
                @foreach ($pilihanKategori as $k)
                    <flux:radio value="{{ $k->value }}" label="{{ $k->label() }}" />
                @endforeach
            </flux:radio.group>

            <x-unggah-foto model="berkas" :label="'Ambil / pilih foto '.App\Enums\KategoriFoto::from($kategori)->label()" />
            <flux:error name="berkas" />
            <flux:error name="order" />
        </div>
    @endif

    @forelse ($perKategori as $kategori => $daftar)
        <div wire:key="kat-{{ $kategori }}">
            <div class="mb-2 text-sm font-medium">{{ App\Enums\KategoriFoto::from($kategori)->label() }}</div>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach ($daftar as $f)
                    <div class="group relative" wire:key="foto-{{ $f->id }}">
                        <a href="{{ berkas_url($f->path) }}" target="_blank">
                            <img src="{{ berkas_url($f->path) }}" alt="" class="aspect-square w-full rounded-lg object-cover" loading="lazy">
                        </a>
                        @if ($bolehUbah)
                            <button type="button" wire:click="hapus({{ $f->id }})" wire:confirm="Hapus foto ini?"
                                class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white" aria-label="Hapus foto">
                                <flux:icon.x-mark variant="micro" />
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-sm text-zinc-500">Belum ada foto.</div>
    @endforelse
</div>
