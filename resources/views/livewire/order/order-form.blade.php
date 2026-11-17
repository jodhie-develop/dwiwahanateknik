<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="$order ? route('order.detail', $order) : route('order.index')" wire:navigate />
        <div>
            <flux:heading size="xl" level="1">{{ $order ? 'Ubah order '.$order->no_order : 'Order kunjungan baru' }}</flux:heading>
            <flux:subheading>Tim ditentukan nanti lewat menu Jadwal Tim → "Bagi tim".</flux:subheading>
        </div>
    </div>

    <form wire:submit="simpan" class="space-y-6">
        @if (auth()->user()->isOwner() && ! $order)
            <div class="sm:w-64">
                <flux:select wire:model.live="cabang_id" label="Cabang">
                    <flux:select.option value="">-- Pilih cabang --</flux:select.option>
                    @foreach ($pilihanCabang as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        @include("livewire.order.partials.pilih-pelanggan-unit", ["bisaGanti" => ! $order])

        {{-- ================= Pekerjaan ================= --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">3. Pekerjaan & jadwal</flux:heading>

            <flux:textarea wire:model="keluhan" label="Keluhan / permintaan" rows="3" required placeholder="AC tidak dingin, minta cuci sekalian" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="tgl_jadwal" label="Tanggal kunjungan" type="date" required />
                <flux:input wire:model="jam_janji" label="Jam janji (opsional)" type="time" description="Hanya info untuk teknisi, tidak mengubah urutan antrian." />
            </div>

            @unless ($order)
                <div class="space-y-3">
                    <flux:label>Layanan (opsional, bisa ditambah teknisi di lokasi)</flux:label>
                    @foreach ($layanan as $i => $baris)
                        <div class="flex items-start gap-2" wire:key="layanan-{{ $i }}">
                            <div class="flex-1">
                                <flux:select wire:model="layanan.{{ $i }}.layanan_id" aria-label="Layanan">
                                    <flux:select.option value="">-- Pilih layanan --</flux:select.option>
                                    @foreach ($pilihanLayanan as $l)
                                        @php($h = $l->harga->first()?->harga)
                                        <flux:select.option value="{{ $l->id }}">{{ $l->jenisPerangkat?->nama }} · {{ $l->nama }} — {{ $h !== null ? rupiah($h) : 'harga belum diatur' }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="layanan.{{ $i }}.layanan_id" />
                            </div>
                            <div class="w-24">
                                <flux:input wire:model="layanan.{{ $i }}.qty" type="number" min="0.01" step="any" aria-label="Jumlah" />
                            </div>
                            <flux:button variant="ghost" icon="x-mark" wire:click="hapusBarisLayanan({{ $i }})" aria-label="Hapus baris" />
                        </div>
                    @endforeach
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="tambahBarisLayanan">Tambah layanan</flux:button>
                </div>
            @endunless

            <flux:textarea wire:model="catatan" label="Catatan internal" rows="2" />
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="$order ? route('order.detail', $order) : route('order.index')" wire:navigate>Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">{{ $order ? 'Simpan perubahan' : 'Simpan order' }}</flux:button>
        </div>
    </form>
</div>
