<div>
    <x-master.toolbar judul="Pelanggan" deskripsi="Data pelanggan berlaku untuk semua cabang." :boleh-tambah="auth()->user()->can('create', App\Models\Pelanggan::class)" label-tambah="Tambah pelanggan" />

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>HP / WA</flux:table.column>
            <flux:table.column class="max-md:hidden">Alamat</flux:table.column>
            <flux:table.column>Unit</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $p)
                <flux:table.row :key="$p->id">
                    <flux:table.cell class="font-medium">
                        <a href="{{ route('master.pelanggan.detail', $p) }}" class="hover:underline" wire:navigate>{{ $p->nama }}</a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $p->hp ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate max-md:hidden">
                        @if ($p->punyaKoordinat())
                            <flux:icon.map-pin variant="micro" class="inline text-green-600" title="Lokasi sudah diisi" />
                        @endif
                        {{ $p->alamat ?: '-' }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $p->unit_count }}</flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$p->aktif" /></flux:table.cell>
                    <flux:table.cell>
                        <x-master.aksi :model="$p" label-hapus="Hapus pelanggan {{ $p->nama }}?">
                            <flux:menu.item icon="eye" :href="route('master.pelanggan.detail', $p)" wire:navigate>Detail & unit</flux:menu.item>
                        </x-master.aksi>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">Belum ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="form-master" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-5">
            <flux:heading size="lg">{{ $editId ? 'Ubah pelanggan' : 'Tambah pelanggan' }}</flux:heading>
            @include('livewire.master.partials.form-pelanggan')
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
