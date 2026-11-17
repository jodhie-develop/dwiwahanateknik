<div>
    <x-master.toolbar judul="Barang" deskripsi="Sparepart dan bahan (freon, pipa, kapasitor, dll). Stok per cabang dikelola di modul Stok." :boleh-tambah="auth()->user()->can('create', App\Models\Barang::class)" label-tambah="Tambah barang" />

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Kode</flux:table.column>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Satuan</flux:table.column>
            <flux:table.column align="end">Harga jual</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $b)
                <flux:table.row :key="$b->id">
                    <flux:table.cell class="font-mono text-sm">{{ $b->kode }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $b->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $b->satuan }}</flux:table.cell>
                    <flux:table.cell align="end">{{ rupiah($b->harga_jual) }}</flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$b->aktif" /></flux:table.cell>
                    <flux:table.cell><x-master.aksi :model="$b" label-hapus="Hapus barang {{ $b->nama }}?" /></flux:table.cell>
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
            <flux:heading size="lg">{{ $editId ? 'Ubah barang' : 'Tambah barang' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="kode" label="Kode" class="uppercase" required />
                <div class="sm:col-span-2">
                    <flux:input wire:model="nama" label="Nama barang" required />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="satuan" label="Satuan" placeholder="pcs / meter / kg" required />
                <flux:input wire:model="harga_jual" label="Harga jual" type="number" min="0" step="1">
                    <x-slot name="iconLeading"><span class="text-sm text-zinc-500">Rp</span></x-slot>
                </flux:input>
            </div>

            <flux:switch wire:model="aktif" label="Aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
