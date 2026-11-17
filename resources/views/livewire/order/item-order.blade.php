<div class="space-y-4">
    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($order->item as $item)
            <div class="flex items-start justify-between gap-3 py-2" wire:key="item-{{ $item->id }}">
                <div class="min-w-0">
                    <div class="font-medium">{{ $item->nama }}</div>
                    <div class="text-xs text-zinc-500">
                        {{ $item->jenis->label() }} · {{ $item->qtyTampil() }} × {{ rupiah($item->harga) }}
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <span class="whitespace-nowrap font-medium">{{ rupiah($item->subtotal) }}</span>
                    @if ($bolehUbah && ($item->jenis !== App\Enums\JenisItem::Biaya || $bolehAturHarga))
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="hapus({{ $item->id }})" wire:confirm="Hapus {{ $item->nama }}?" aria-label="Hapus" />
                    @endif
                </div>
            </div>
        @empty
            <div class="py-3 text-sm text-zinc-500">Belum ada item layanan/barang.</div>
        @endforelse
    </div>

    <div class="space-y-1 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($order->subtotal) }}</span></div>
        @if ($bolehAturHarga && ! $order->terkunci())
            <form wire:submit="simpanDiskon" class="flex items-center justify-between gap-2">
                <span>Diskon</span>
                <div class="flex items-center gap-1">
                    <div class="w-36"><flux:input wire:model="diskon" type="number" min="0" size="sm" aria-label="Diskon" /></div>
                    <flux:button size="sm" type="submit" variant="ghost">Simpan</flux:button>
                </div>
            </form>
            <flux:error name="diskon" />
        @elseif ($order->diskon)
            <div class="flex justify-between"><span>Diskon</span><span>- {{ rupiah($order->diskon) }}</span></div>
        @endif
        <div class="flex justify-between text-base font-semibold"><span>Total</span><span>{{ rupiah($order->total) }}</span></div>
    </div>

    @if ($bolehUbah)
        <form wire:submit="tambah" class="space-y-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-700/40">
            <flux:radio.group wire:model.live="jenis" variant="segmented" size="sm">
                <flux:radio value="layanan" label="Layanan" />
                <flux:radio value="barang" label="Barang / sparepart" />
            </flux:radio.group>

            <flux:select wire:model="sumber_id" aria-label="Pilih item">
                <flux:select.option value="">-- Pilih {{ $jenis === 'barang' ? 'barang' : 'layanan' }} --</flux:select.option>
                @foreach ($pilihan as $p)
                    <flux:select.option value="{{ $p['id'] }}">{{ $p['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="sumber_id" />

            <div class="flex items-end gap-2">
                <div class="w-28"><flux:input wire:model="qty" type="number" min="0.01" step="any" label="Jumlah" /></div>
                @if ($bolehAturHarga)
                    <div class="flex-1"><flux:input wire:model="harga" type="number" min="0" label="Harga (kosong = harga master)" /></div>
                @endif
                <flux:button type="submit" variant="primary" icon="plus" class="{{ $mobile ? 'flex-1' : '' }}">Tambah</flux:button>
            </div>
            <flux:error name="qty" />
            <flux:error name="order" />
        </form>
    @endif
</div>
