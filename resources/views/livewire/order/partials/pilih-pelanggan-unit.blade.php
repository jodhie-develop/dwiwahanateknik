{{-- Pilih pelanggan & unit. Butuh trait PilihPelangganUnit + IsiKoordinat.
     Variabel: $hasilCari, $pelanggan, $daftarUnit, $pilihanJenis, $bisaGanti (bool), $judulUnit, $alamatWajib (bool) --}}
@php($bisaGanti ??= true)
@php($judulUnit ??= '2. Unit yang diservis')
@php($alamatWajib ??= true)

{{-- ================= Pelanggan ================= --}}
<flux:card class="space-y-4">
    <flux:heading size="lg">1. Pelanggan</flux:heading>

    @if ($pelanggan)
        <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <div>
                <div class="font-medium">{{ $pelanggan->nama }}</div>
                <div class="text-sm text-zinc-500">{{ $pelanggan->hp ?: '-' }} · {{ $pelanggan->alamat ?: '-' }}</div>
                @if ($alamatWajib && ! $pelanggan->punyaKoordinat())
                    <div class="mt-1 text-xs text-amber-600">Lokasi pelanggan belum diisi; rekomendasi tim berdasarkan jarak tidak bisa dipakai.</div>
                @endif
            </div>
            @if ($bisaGanti)
                <flux:button size="sm" variant="ghost" wire:click="gantiPelanggan">Ganti</flux:button>
            @endif
        </div>
    @elseif ($pelangganBaru)
        <div class="space-y-4 rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
            <div class="flex items-center justify-between">
                <flux:heading>Pelanggan baru</flux:heading>
                <flux:button size="sm" variant="ghost" wire:click="gantiPelanggan">Batal</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="pb_nama" label="Nama" required />
                <flux:input wire:model="pb_hp" label="No. HP / WA" type="tel" placeholder="081234567890" />
            </div>
            <flux:textarea wire:model="pb_alamat" label="{{ $alamatWajib ? 'Alamat' : 'Alamat (opsional)' }}" rows="2" :required="$alamatWajib" />
            @include('livewire.master.partials.input-koordinat')
        </div>
    @else
        <div class="space-y-2">
            <flux:input wire:model.live.debounce.300ms="cariPelanggan" icon="magnifying-glass" placeholder="Cari nama / no. HP / alamat pelanggan (min. 2 huruf)" autofocus />
            <flux:error name="pelanggan_id" />

            @if ($hasilCari->isNotEmpty())
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($hasilCari as $p)
                        <button type="button" wire:click="pilihPelanggan({{ $p->id }})" wire:key="cari-{{ $p->id }}"
                            class="block w-full px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <div class="font-medium">{{ $p->nama }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $p->hp ?: '-' }} · {{ $p->alamat ?: '-' }}</div>
                        </button>
                    @endforeach
                </div>
            @elseif (mb_strlen(trim($cariPelanggan)) >= 2)
                <flux:text class="text-sm">Pelanggan tidak ditemukan.</flux:text>
            @endif

            <flux:button size="sm" icon="user-plus" wire:click="mulaiPelangganBaru">Pelanggan baru</flux:button>
        </div>
    @endif
</flux:card>

{{-- ================= Unit ================= --}}
@if ($pelanggan || $pelangganBaru)
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ $judulUnit }}</flux:heading>
            @if ($pelanggan && $daftarUnit->isNotEmpty() && $bisaGanti)
                <flux:button size="sm" variant="ghost" wire:click="$toggle('unitBaru')">{{ $unitBaru ? 'Pilih unit yang ada' : '+ Unit baru' }}</flux:button>
            @endif
        </div>

        @if (! $unitBaru)
            <flux:radio.group wire:model.live="unit_pelanggan_id" variant="cards" class="flex-col">
                @foreach ($daftarUnit as $u)
                    <flux:radio value="{{ $u->id }}" wire:key="unit-{{ $u->id }}"
                        label="{{ $u->jenisPerangkat?->nama }} {{ trim($u->merk.' '.$u->model) }} {{ $u->ukuran ? '· '.$u->ukuran : '' }}"
                        description="{{ $u->lokasi ?: 'Lokasi unit belum diisi' }}{{ $u->nomor_seri ? ' · SN '.$u->nomor_seri : '' }}{{ $u->alamat ? ' · '.$u->alamat : '' }}" />
                @endforeach
            </flux:radio.group>
            <flux:error name="unit_pelanggan_id" />
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="ub_jenis_perangkat_id" label="Jenis perangkat">
                    <flux:select.option value="">-- Pilih jenis --</flux:select.option>
                    @foreach ($pilihanJenis as $j)
                        <flux:select.option value="{{ $j->id }}">{{ $j->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="ub_merk" label="Merk" />
                <flux:input wire:model="ub_model" label="Model / tipe" />
                <flux:input wire:model="ub_ukuran" label="Ukuran / PK" placeholder="1 PK, 32 inch" />
                <flux:input wire:model="ub_nomor_seri" label="Nomor seri" />
                <flux:input wire:model="ub_lokasi" label="Lokasi di rumah (opsional)" placeholder="Kamar depan" />
            </div>
        @endif
    </flux:card>
@endif
