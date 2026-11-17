@props(['judul', 'deskripsi' => null, 'bolehTambah' => false, 'labelTambah' => 'Tambah'])

<div class="mb-4 flex flex-col gap-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ $judul }}</flux:heading>
            @if ($deskripsi)
                <flux:subheading>{{ $deskripsi }}</flux:subheading>
            @endif
        </div>

        @if ($bolehTambah)
            <flux:button variant="primary" icon="plus" wire:click="tambah">{{ $labelTambah }}</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="sm:w-80">
            <flux:input wire:model.live.debounce.400ms="cari" icon="magnifying-glass" placeholder="Cari..." clearable />
        </div>
        <div class="sm:w-44">
            <flux:select wire:model.live="status">
                <flux:select.option value="aktif">Aktif</flux:select.option>
                <flux:select.option value="nonaktif">Nonaktif</flux:select.option>
                <flux:select.option value="semua">Semua</flux:select.option>
            </flux:select>
        </div>
        {{ $slot }}
    </div>
</div>
