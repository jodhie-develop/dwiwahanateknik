<div>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Order Bengkel</flux:heading>
            <flux:subheading>Barang servis yang dibawa ke cabang.</flux:subheading>
        </div>
        @can('create', App\Models\OrderServis::class)
            <flux:button variant="primary" icon="plus" :href="route('bengkel.terima')" wire:navigate>Terima barang</flux:button>
        @endcan
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.400ms="cari" icon="magnifying-glass" placeholder="No. servis / nama / HP" clearable />
        </div>
        <div class="w-full sm:w-64">
            <flux:select wire:model.live="tampil" aria-label="Tampilkan">
                <flux:select.option value="proses">Dalam proses (belum diserahkan)</flux:select.option>
                <flux:select.option value="belum_diambil">Belum diambil > {{ $batasHari }} hari ({{ $jumlahBelumDiambil }})</flux:select.option>
                <flux:select.option value="semua">Semua</flux:select.option>
                @foreach ($pilihanStatus as $s)
                    <flux:select.option value="{{ $s->value }}">Status: {{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($jumlahBelumDiambil > 0 && $tampil !== 'belum_diambil')
            <flux:button variant="ghost" icon="exclamation-triangle" class="text-amber-600" wire:click="$set('tampil', 'belum_diambil')">
                {{ $jumlahBelumDiambil }} barang belum diambil
            </flux:button>
        @endif
    </div>

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>No. servis</flux:table.column>
            <flux:table.column>Diterima</flux:table.column>
            <flux:table.column>Pelanggan</flux:table.column>
            <flux:table.column class="max-lg:hidden">Barang</flux:table.column>
            <flux:table.column class="max-md:hidden">Teknisi</flux:table.column>
            <flux:table.column class="max-md:hidden">Rak</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            @if ($tampil === 'belum_diambil')
                <flux:table.column></flux:table.column>
            @endif
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $o)
                <flux:table.row :key="$o->id">
                    <flux:table.cell>
                        <a href="{{ route('bengkel.detail', $o) }}" class="font-mono text-sm font-medium hover:underline" wire:navigate>{{ $o->no_order }}</a>
                    </flux:table.cell>
                    <flux:table.cell>{{ tgl($o->created_at) }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $o->pelanggan?->nama }}</div>
                        <div class="text-xs text-zinc-500">{{ $o->pelanggan?->hp }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-lg:hidden">{{ $o->unit?->jenisPerangkat?->nama }} {{ $o->unit?->merk }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $o->teknisi->pluck('teknisi.nama')->implode(', ') ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $o->penerimaan?->lokasi_simpan ?: '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$o->status->warna()" inset="top bottom">{{ $o->status->label() }}</flux:badge>
                        @if ($o->status === App\Enums\StatusOrder::Selesai && $o->selesai_at)
                            <div class="text-xs text-zinc-500">selesai {{ tgl($o->selesai_at) }} ({{ (int) $o->selesai_at->diffInDays(now()) }} hari)</div>
                        @endif
                    </flux:table.cell>
                    @if ($tampil === 'belum_diambil')
                        <flux:table.cell>
                            @if ($wa = $linkPengingat($o))
                                <flux:button size="sm" icon="chat-bubble-left-right" :href="$wa" target="_blank">Ingatkan via WA</flux:button>
                            @else
                                <span class="text-xs text-zinc-500">No. HP kosong</span>
                            @endif
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500">Tidak ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
