<div>
    <x-master.toolbar judul="Jenis Perangkat" deskripsi="AC, TV, kulkas, mesin cuci, dan lainnya." :boleh-tambah="auth()->user()->can('create', App\Models\JenisPerangkat::class)" label-tambah="Tambah jenis" />

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Jumlah layanan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $j)
                <flux:table.row :key="$j->id">
                    <flux:table.cell class="font-medium">{{ $j->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $j->layanan_count }}</flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$j->aktif" /></flux:table.cell>
                    <flux:table.cell><x-master.aksi :model="$j" label-hapus="Hapus jenis perangkat {{ $j->nama }}?" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">Belum ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="form-master" class="w-full max-w-md">
        <form wire:submit="simpan" class="space-y-5">
            <flux:heading size="lg">{{ $editId ? 'Ubah jenis perangkat' : 'Tambah jenis perangkat' }}</flux:heading>
            <flux:input wire:model="nama" label="Nama" placeholder="AC" required />
            <flux:switch wire:model="aktif" label="Aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
