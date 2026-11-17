{{-- Tombol aksi per baris. $model wajib punya kolom aktif. --}}
@props(['model', 'labelHapus' => null])

<div class="flex justify-end">
    @canany(['update', 'delete'], $model)
        <flux:dropdown position="bottom" align="end">
            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Aksi" />

            <flux:menu>
                {{ $slot }}

                @can('update', $model)
                    <flux:menu.item icon="pencil-square" wire:click="ubah({{ $model->id }})">Ubah</flux:menu.item>
                    <flux:menu.item icon="{{ $model->aktif ? 'eye-slash' : 'eye' }}" wire:click="toggleAktif({{ $model->id }})">
                        {{ $model->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                    </flux:menu.item>
                @endcan

                @can('delete', $model)
                    <flux:menu.separator />
                    <flux:menu.item icon="trash" variant="danger"
                        wire:click="hapus({{ $model->id }})"
                        wire:confirm="{{ $labelHapus ?? 'Hapus data ini?' }}">Hapus</flux:menu.item>
                @endcan
            </flux:menu>
        </flux:dropdown>
    @endcanany
</div>
