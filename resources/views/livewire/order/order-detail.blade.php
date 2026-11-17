@php($o = $order)
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('order.index')" wire:navigate />
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1" class="font-mono">{{ $o->no_order }}</flux:heading>
                    <flux:badge :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
                </div>
                <flux:subheading>
                    {{ $o->tipe->label() }} · {{ tgl($o->tgl_jadwal) }}{{ $o->jam_janji ? ', janji '.substr($o->jam_janji, 0, 5) : '' }}
                    · {{ $o->cabang?->nama }} · dibuat {{ $o->pembuat?->nama }} {{ tgl($o->created_at, true) }}
                </flux:subheading>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($o->status === App\Enums\StatusOrder::Dijadwalkan)
                @can('update', $o)
                    <flux:button icon="pencil-square" :href="route('order.ubah', $o)" wire:navigate>Ubah</flux:button>
                @endcan
            @endif
            @foreach ($tujuanStatus as $s)
                <flux:button variant="primary" wire:click="ubahStatus('{{ $s->value }}')" wire:confirm="Ubah status menjadi {{ $s->label() }}?">{{ $s->label() }}</flux:button>
            @endforeach
            @if ($bisaBatal)
                <flux:modal.trigger name="batal-order">
                    <flux:button variant="danger" icon="x-circle">Batalkan</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    @if (session('sukses'))
        <flux:callout variant="success" icon="check-circle" :heading="session('sukses')" />
    @endif
    <flux:error name="status" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Pelanggan & unit --}}
            <flux:card class="space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('master.pelanggan.detail', $o->pelanggan_id) }}" class="text-lg font-semibold hover:underline" wire:navigate>{{ $o->pelanggan?->nama }}</a>
                        <div class="text-sm text-zinc-500">{{ $o->pelanggan?->hp ?: '-' }}</div>
                    </div>
                    @if ($k = $o->koordinat())
                        <flux:button size="sm" icon="map-pin" :href="App\Support\KoordinatMaps::urlMaps($k['lat'], $k['lng'])" target="_blank">Maps</flux:button>
                    @endif
                </div>
                <div class="text-sm">{{ $o->unit?->alamatEfektif() ?: '-' }}</div>
                <flux:separator variant="subtle" />
                <div class="text-sm">
                    <span class="font-medium">{{ $o->unit?->jenisPerangkat?->nama }} {{ trim($o->unit?->merk.' '.$o->unit?->model) }}</span>
                    {{ $o->unit?->ukuran ? '· '.$o->unit->ukuran : '' }} {{ $o->unit?->lokasi ? '· '.$o->unit->lokasi : '' }}
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Keluhan</div>
                    <p class="whitespace-pre-line">{{ $o->keluhan }}</p>
                </div>
                @if ($o->catatan)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Catatan internal</div>
                        <p class="whitespace-pre-line text-sm">{{ $o->catatan }}</p>
                    </div>
                @endif
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Layanan & barang</flux:heading>
                <livewire:order.item-order :order-id="$o->id" :key="'item-'.$o->id" />
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Foto</flux:heading>
                <livewire:order.foto-order :order-id="$o->id" :key="'foto-'.$o->id" />
            </flux:card>
        </div>

        <div class="space-y-6">
            {{-- Penugasan --}}
            <flux:card class="space-y-4">
                <flux:heading size="lg">Tim & teknisi</flux:heading>

                @if ($bisaAturTim)
                    <form wire:submit="simpanTim" class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:select wire:model="tim_id" label="Tim">
                                <flux:select.option value="">-- Belum dibagi --</flux:select.option>
                                @foreach ($pilihanTim as $t)
                                    <flux:select.option value="{{ $t->id }}">{{ $t->nama_tim }} ({{ $t->anggotaAktif->pluck('teknisi.nama')->implode(', ') ?: 'tanpa anggota' }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button type="submit">Tetapkan</flux:button>
                    </form>
                    <flux:error name="tim_id" />

                    @if ($o->tim_id)
                        <form wire:submit="simpanPelaksana" class="space-y-2">
                            <flux:label>Teknisi pelaksana (bisa diganti untuk order ini)</flux:label>
                            @foreach ($pilihanTeknisi as $tk)
                                <div class="flex items-center justify-between gap-2" wire:key="pel-{{ $tk->id }}">
                                    <span class="text-sm">{{ $tk->nama }}</span>
                                    <div class="w-32">
                                        <flux:select wire:model="pelaksana.{{ $tk->id }}" size="sm" aria-label="Peran {{ $tk->nama }}">
                                            <flux:select.option value="">Tidak ikut</flux:select.option>
                                            <flux:select.option value="ketua">Ketua</flux:select.option>
                                            <flux:select.option value="anggota">Anggota</flux:select.option>
                                        </flux:select>
                                    </div>
                                </div>
                            @endforeach
                            <flux:error name="teknisi" />
                            <flux:button type="submit" size="sm" class="w-full">Simpan pelaksana</flux:button>
                        </form>
                    @endif
                @else
                    <div class="text-sm">Tim: <span class="font-medium">{{ $o->tim?->nama_tim ?? 'Belum dibagi' }}</span></div>
                    <div class="space-y-1">
                        @forelse ($o->teknisi as $ot)
                            <div class="text-sm">{{ $ot->teknisi?->nama }} <span class="text-zinc-500">· {{ $ot->peran->label() }}</span></div>
                        @empty
                            <div class="text-sm text-zinc-500">Belum ada teknisi.</div>
                        @endforelse
                    </div>
                @endif
            </flux:card>

            {{-- Lapangan --}}
            <flux:card class="space-y-3">
                <flux:heading size="lg">Data lapangan</flux:heading>
                <div class="text-sm">
                    Check-in:
                    @if ($o->checkin_at)
                        {{ tgl($o->checkin_at, true) }}
                        <a href="{{ App\Support\KoordinatMaps::urlMaps($o->checkin_lat, $o->checkin_lng) }}" target="_blank" class="text-blue-600 hover:underline">(lihat titik)</a>
                    @else
                        <span class="text-zinc-500">belum</span>
                    @endif
                </div>
                <div class="text-sm">Tanda tangan pelanggan:</div>
                @if ($o->tanda_tangan)
                    <img src="{{ berkas_url($o->tanda_tangan) }}" alt="Tanda tangan" class="h-24 rounded border border-zinc-200 bg-white p-1 dark:border-zinc-700">
                @else
                    <div class="text-sm text-zinc-500">belum ada</div>
                @endif
            </flux:card>

            {{-- Riwayat --}}
            <flux:card class="space-y-3">
                <flux:heading size="lg">Riwayat</flux:heading>
                <ol class="space-y-3 text-sm">
                    @foreach ($o->log->sortByDesc('id') as $log)
                        <li wire:key="log-{{ $log->id }}">
                            <div class="font-medium">
                                @if ($log->dari_status && $log->dari_status !== $log->ke_status)
                                    {{ $log->dari_status->label() }} → {{ $log->ke_status->label() }}
                                @else
                                    {{ $log->catatan ?? $log->ke_status->label() }}
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500">{{ tgl($log->created_at, true) }} · {{ $log->user?->nama ?? 'sistem' }}</div>
                            @if ($log->catatan && $log->dari_status && $log->dari_status !== $log->ke_status)
                                <div class="text-xs italic">{{ $log->catatan }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </flux:card>
        </div>
    </div>

    <flux:modal name="batal-order" class="w-full max-w-md">
        <form wire:submit="batalkan" class="space-y-4">
            <flux:heading size="lg">Batalkan order {{ $o->no_order }}?</flux:heading>
            <flux:textarea wire:model="alasanBatal" label="Alasan pembatalan" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Batalkan order</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
