<div>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Order Kunjungan</flux:heading>
            <flux:subheading>Urutan antrian mengikuti waktu input. Bagikan ke tim lewat menu Jadwal Tim.</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="calendar-days" :href="route('order.jadwal')" wire:navigate>Jadwal Tim</flux:button>
            @can('create', App\Models\OrderServis::class)
                <flux:button variant="primary" icon="plus" :href="route('order.baru')" wire:navigate>Order baru</flux:button>
            @endcan
        </div>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live.debounce.400ms="cari" icon="magnifying-glass" placeholder="No. order / nama / HP" clearable />
        <flux:input wire:model.live="tanggal" type="date" aria-label="Tanggal" />
        <flux:select wire:model.live="status" aria-label="Status">
            <flux:select.option value="">Semua status</flux:select.option>
            @foreach ($pilihanStatus as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="tim" aria-label="Tim">
            <flux:select.option value="">Semua tim</flux:select.option>
            <flux:select.option value="belum">Belum dibagi</flux:select.option>
            @foreach ($pilihanTim as $t)
                <flux:select.option value="{{ $t->id }}">{{ $t->nama_tim }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>No. order</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Pelanggan</flux:table.column>
            <flux:table.column class="max-lg:hidden">Unit</flux:table.column>
            <flux:table.column>Tim</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end" class="max-md:hidden">Total</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $o)
                <flux:table.row :key="$o->id">
                    <flux:table.cell>
                        <a href="{{ route('order.detail', $o) }}" class="font-mono text-sm font-medium hover:underline" wire:navigate>{{ $o->no_order }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ tgl($o->tgl_jadwal) }}
                        @if ($o->jam_janji)
                            <div class="text-xs text-zinc-500">janji {{ substr($o->jam_janji, 0, 5) }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-w-56 truncate">
                        <div class="font-medium">{{ $o->pelanggan?->nama }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $o->pelanggan?->alamat }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-lg:hidden">{{ $o->unit?->jenisPerangkat?->nama }} {{ $o->unit?->merk }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($o->tim)
                            {{ $o->tim->nama_tim }}
                        @else
                            <flux:badge size="sm" color="amber" inset="top bottom">Belum dibagi</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$o->status->warna()" inset="top bottom">{{ $o->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end" class="max-md:hidden">{{ rupiah($o->total) }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">Belum ada order.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
