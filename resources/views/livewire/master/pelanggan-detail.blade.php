<div class="space-y-6">
    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('master.pelanggan')" wire:navigate>Daftar pelanggan</flux:button>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ $pelanggan->nama }}</flux:heading>
            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600 dark:text-zinc-300">
                @if ($pelanggan->hp)
                    <span class="inline-flex items-center gap-1"><flux:icon.phone variant="micro" /> {{ $pelanggan->hp }}</span>
                @endif
                @if ($pelanggan->punyaKoordinat())
                    <a href="{{ App\Support\KoordinatMaps::urlMaps($pelanggan->latitude, $pelanggan->longitude) }}" target="_blank" class="inline-flex items-center gap-1 hover:underline">
                        <flux:icon.map-pin variant="micro" class="text-green-600" /> Lihat lokasi di Maps
                    </a>
                @else
                    <span class="inline-flex items-center gap-1 text-zinc-400"><flux:icon.map-pin variant="micro" /> Lokasi belum diisi</span>
                @endif
                <x-status-aktif :aktif="$pelanggan->aktif" />
            </div>
            @if ($pelanggan->alamat)
                <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">{{ $pelanggan->alamat }}</p>
            @endif
            @if ($pelanggan->catatan)
                <p class="max-w-2xl text-sm italic text-zinc-500">{{ $pelanggan->catatan }}</p>
            @endif
        </div>
    </div>

    <flux:separator />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">Unit / perangkat</flux:heading>
        <div class="flex items-center gap-3">
            <flux:switch wire:model.live="tampilNonaktif" label="Tampilkan nonaktif" />
            @can('create', App\Models\UnitPelanggan::class)
                <flux:button variant="primary" icon="plus" wire:click="tambahUnit">Tambah unit</flux:button>
            @endcan
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Jenis</flux:table.column>
            <flux:table.column>Merk / model</flux:table.column>
            <flux:table.column class="max-md:hidden">Ukuran</flux:table.column>
            <flux:table.column class="max-md:hidden">No. seri</flux:table.column>
            <flux:table.column>Lokasi</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftarUnit as $u)
                <flux:table.row :key="$u->id">
                    <flux:table.cell class="font-medium">{{ $u->jenisPerangkat?->nama }}</flux:table.cell>
                    <flux:table.cell>{{ trim($u->merk.' '.$u->model) ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $u->ukuran ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $u->nomor_seri ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $u->lokasi ?: '-' }}
                        @if ($u->alamat)
                            <div class="text-xs text-zinc-500">{{ $u->alamat }}</div>
                        @endif
                        @if ($u->latitude !== null)
                            <a href="{{ App\Support\KoordinatMaps::urlMaps($u->latitude, $u->longitude) }}" target="_blank" class="inline-flex items-center gap-1 text-xs hover:underline">
                                <flux:icon.map-pin variant="micro" class="text-green-600" /> Lokasi unit
                            </a>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$u->aktif" /></flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Aksi" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="ubahUnit({{ $u->id }})">Ubah</flux:menu.item>
                                    <flux:menu.item icon="{{ $u->aktif ? 'eye-slash' : 'eye' }}" wire:click="toggleAktifUnit({{ $u->id }})">{{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="hapusUnit({{ $u->id }})" wire:confirm="Hapus unit ini?">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">Belum ada unit.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="form-unit" class="w-full max-w-lg">
        <form wire:submit="simpanUnit" class="space-y-5">
            <flux:heading size="lg">{{ $unitId ? 'Ubah unit' : 'Tambah unit' }}</flux:heading>

            <flux:select wire:model="jenis_perangkat_id" label="Jenis perangkat">
                <flux:select.option value="">-- Pilih jenis --</flux:select.option>
                @foreach ($pilihanJenis as $j)
                    <flux:select.option value="{{ $j->id }}">{{ $j->nama }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="merk" label="Merk" />
                <flux:input wire:model="model" label="Model / tipe" />
                <flux:input wire:model="ukuran" label="Ukuran / PK" placeholder="1 PK, 32 inch" />
                <flux:input wire:model="nomor_seri" label="Nomor seri" />
            </div>

            <flux:input wire:model="lokasi" label="Lokasi" placeholder="Kamar depan" />
            <flux:textarea wire:model="alamat" label="Alamat unit" rows="2" description="Isi hanya jika berbeda dengan alamat pelanggan." />
            @include('livewire.master.partials.input-koordinat', ['keterangan' => 'Isi hanya jika lokasinya berbeda dengan lokasi pelanggan.'])
            <flux:switch wire:model="aktif" label="Aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
