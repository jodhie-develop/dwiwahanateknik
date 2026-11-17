<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">Pengaturan</flux:heading>
        <flux:subheading>Pengaturan operasional cabang.</flux:subheading>
    </div>

    @if ($pilihanCabang->isNotEmpty())
        <div class="sm:w-72">
            <flux:select wire:model.live="lingkup" label="Berlaku untuk">
                <flux:select.option value="">Default semua cabang</flux:select.option>
                @foreach ($pilihanCabang as $c)
                    <flux:select.option value="{{ $c->id }}">Cabang {{ $c->nama }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:text class="mt-1 text-xs">Pengaturan cabang menimpa nilai default.</flux:text>
        </div>
    @else
        <flux:text>Cabang: <strong>{{ $namaCabang }}</strong></flux:text>
    @endif

    <form wire:submit="simpan" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading size="lg">Servis bengkel</flux:heading>

            <flux:switch wire:model.live="biaya_cek_aktif" label="Tagih biaya cek" description="Otomatis ditambahkan saat barang diterima dan tetap ditagih walaupun pelanggan batal memperbaiki." />

            @if ($biaya_cek_aktif)
                <div class="sm:w-64">
                    <flux:input wire:model="biaya_cek_nominal" type="number" min="0" label="Nominal biaya cek">
                        <x-slot name="iconLeading"><span class="text-sm text-zinc-500">Rp</span></x-slot>
                    </flux:input>
                </div>
            @endif

            <div class="sm:w-64">
                <flux:input wire:model="batas_hari_ambil" type="number" min="1" max="365" label="Batas hari pengambilan" description="Barang selesai yang belum diambil lewat batas ini masuk daftar follow-up." />
            </div>
        </flux:card>

        <flux:button type="submit" variant="primary">Simpan pengaturan</flux:button>
    </form>
</div>
