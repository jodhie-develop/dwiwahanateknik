<div>
    <x-master.toolbar judul="Cabang" deskripsi="Daftar cabang usaha. Kode dipakai di nomor dokumen (mis. SRV/SBY/...)." :boleh-tambah="auth()->user()->can('create', App\Models\Cabang::class)" label-tambah="Tambah cabang" />

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Kode</flux:table.column>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column class="max-md:hidden">Alamat</flux:table.column>
            <flux:table.column class="max-md:hidden">Telp</flux:table.column>
            <flux:table.column>Pengguna</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $c)
                <flux:table.row :key="$c->id">
                    <flux:table.cell class="font-medium">{{ $c->kode }}</flux:table.cell>
                    <flux:table.cell>{{ $c->nama }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate max-md:hidden">{{ $c->alamat ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $c->telp ?: '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $c->users_count }}</flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$c->aktif" /></flux:table.cell>
                    <flux:table.cell><x-master.aksi :model="$c" label-hapus="Hapus cabang {{ $c->nama }}?" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">Belum ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="form-master" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-5">
            <flux:heading size="lg">{{ $editId ? 'Ubah cabang' : 'Tambah cabang' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="kode" label="Kode" placeholder="SBY" class="uppercase" required />
                <div class="sm:col-span-2">
                    <flux:input wire:model="nama" label="Nama cabang" required />
                </div>
            </div>
            <flux:textarea wire:model="alamat" label="Alamat" rows="2" />
            @include('livewire.master.partials.input-koordinat', ['keterangan' => 'Titik kantor: awal rute teknisi sebelum job pertama.'])
            <flux:input wire:model="telp" label="Telepon" type="tel" />
            <flux:switch wire:model="aktif" label="Aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
