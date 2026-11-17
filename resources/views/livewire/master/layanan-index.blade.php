<div>
    <x-master.toolbar judul="Layanan & Harga" deskripsi="Daftar layanan per jenis perangkat. Harga bisa berbeda di tiap cabang." :boleh-tambah="auth()->user()->can('create', App\Models\Layanan::class)" label-tambah="Tambah layanan">
        <div class="sm:w-52">
            <flux:select wire:model.live="filterJenis">
                <flux:select.option value="">Semua jenis</flux:select.option>
                @foreach ($pilihanJenis as $j)
                    <flux:select.option value="{{ $j->id }}">{{ $j->nama }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-master.toolbar>

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Layanan</flux:table.column>
            <flux:table.column>Jenis</flux:table.column>
            <flux:table.column class="max-md:hidden">Tipe</flux:table.column>
            <flux:table.column class="max-md:hidden">Garansi</flux:table.column>
            <flux:table.column class="max-md:hidden">Reminder</flux:table.column>
            <flux:table.column align="end">Harga{{ $cabangTampil ? '' : ' (per cabang)' }}</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $l)
                <flux:table.row :key="$l->id">
                    <flux:table.cell class="font-medium">{{ $l->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $l->jenisPerangkat?->nama }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $l->tipe->label() }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $l->masa_garansi_hari ? $l->masa_garansi_hari.' hari' : '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $l->interval_reminder_hari ? $l->interval_reminder_hari.' hari' : '-' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        {{-- relasi harga sudah dibatasi CabangScope --}}
                        @if ($cabangTampil)
                            {{ ($h = $l->harga->firstWhere('cabang_id', $cabangTampil)) ? rupiah($h->harga) : 'Belum diatur' }}
                        @elseif ($l->harga->isEmpty())
                            <span class="text-zinc-500">Belum diatur</span>
                        @elseif ($l->harga->min('harga') === $l->harga->max('harga'))
                            {{ rupiah($l->harga->min('harga')) }}
                        @else
                            {{ rupiah($l->harga->min('harga')) }} – {{ rupiah($l->harga->max('harga')) }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$l->aktif" /></flux:table.cell>
                    <flux:table.cell>
                        @can('update', $l)
                            <x-master.aksi :model="$l" label-hapus="Hapus layanan {{ $l->nama }}?">
                                <flux:menu.item icon="banknotes" wire:click="aturHarga({{ $l->id }})">Atur harga</flux:menu.item>
                            </x-master.aksi>
                        @elsecan('aturHarga', $l)
                            <div class="flex justify-end">
                                <flux:button size="sm" variant="ghost" icon="banknotes" wire:click="aturHarga({{ $l->id }})">Harga</flux:button>
                            </div>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500">Belum ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Form layanan --}}
    <flux:modal name="form-master" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-5">
            <flux:heading size="lg">{{ $editId ? 'Ubah layanan' : 'Tambah layanan' }}</flux:heading>

            <flux:select wire:model="jenis_perangkat_id" label="Jenis perangkat">
                <flux:select.option value="">-- Pilih jenis --</flux:select.option>
                @foreach ($pilihanJenis as $j)
                    <flux:select.option value="{{ $j->id }}">{{ $j->nama }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="nama" label="Nama layanan" placeholder="Cuci AC" required />

            <flux:select wire:model="tipe" label="Berlaku untuk">
                @foreach ($pilihanTipe as $nilai => $label)
                    <flux:select.option value="{{ $nilai }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="masa_garansi_hari" label="Masa garansi (hari)" type="number" min="0" />
                <flux:input wire:model="interval_reminder_hari" label="Reminder servis ulang (hari)" type="number" min="1" description="Kosongkan jika tidak ada." />
            </div>

            <flux:switch wire:model="aktif" label="Aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Harga per cabang --}}
    <flux:modal name="harga-layanan" class="w-full max-w-md">
        <form wire:submit="simpanHarga" class="space-y-5">
            <div>
                <flux:heading size="lg">Harga layanan</flux:heading>
                <flux:subheading>{{ $layananHarga?->nama }}</flux:subheading>
            </div>

            @foreach ($cabangHarga as $c)
                <flux:input wire:model="harga.{{ $c->id }}" wire:key="harga-{{ $c->id }}" label="{{ $c->nama }} ({{ $c->kode }})" type="number" min="0" step="1" placeholder="Belum diatur">
                    <x-slot name="iconLeading"><span class="text-sm text-zinc-500">Rp</span></x-slot>
                </flux:input>
            @endforeach

            <flux:text class="text-xs">Kosongkan untuk menghapus harga di cabang tersebut.</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan harga</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
