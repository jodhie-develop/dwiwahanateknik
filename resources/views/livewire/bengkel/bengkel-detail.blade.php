<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('bengkel.index')" wire:navigate />
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1" class="font-mono">{{ $o->no_order }}</flux:heading>
                    <flux:badge :color="$o->status->warna()">{{ $o->status->label() }}</flux:badge>
                </div>
                <flux:subheading>
                    Bengkel · diterima {{ tgl($o->created_at, true) }} oleh {{ $o->penerimaan?->penerima?->nama ?? $o->pembuat?->nama }} · {{ $o->cabang?->nama }}
                </flux:subheading>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:dropdown>
                <flux:button icon="printer" icon:trailing="chevron-down">Cetak</flux:button>
                <flux:menu>
                    <flux:menu.item icon="document-text" :href="route('bengkel.cetak.tanda-terima', $o)" target="_blank">Tanda terima (A5 ×2)</flux:menu.item>
                    <flux:menu.item icon="tag" :href="route('bengkel.cetak.label', $o)" target="_blank">Label nomor servis (A4)</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            @if ($linkWaTandaTerima)
                <flux:button icon="chat-bubble-left-right" :href="$linkWaTandaTerima" target="_blank">Kirim via WA</flux:button>
            @endif
            @foreach ($tujuanStatus as $s)
                @php($label = match ($s) { App\Enums\StatusOrder::Diagnosa => 'Mulai diagnosa', App\Enums\StatusOrder::MenungguSparepart => 'Tunggu sparepart', App\Enums\StatusOrder::Dikerjakan => 'Lanjut kerjakan', App\Enums\StatusOrder::Diambil => 'Diambil pelanggan', App\Enums\StatusOrder::Dikembalikan => 'Dikembalikan ke pelanggan', default => $s->label() })
                <flux:button variant="primary" wire:click="ubahStatus('{{ $s->value }}')" wire:confirm="{{ $label }}?">{{ $label }}</flux:button>
            @endforeach
        </div>
    </div>

    @if (session('sukses'))
        <flux:callout variant="success" icon="check-circle" :heading="session('sukses')" />
    @endif
    <flux:error name="status" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-3">
                <div>
                    <a href="{{ route('master.pelanggan.detail', $o->pelanggan_id) }}" class="text-lg font-semibold hover:underline" wire:navigate>{{ $o->pelanggan?->nama }}</a>
                    <div class="text-sm text-zinc-500">{{ $o->pelanggan?->hp ?: '-' }} · {{ $o->pelanggan?->alamat ?: '-' }}</div>
                </div>
                <flux:separator variant="subtle" />
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Barang</div>
                        {{ $o->unit?->jenisPerangkat?->nama }} {{ trim($o->unit?->merk.' '.$o->unit?->model) }}
                        {{ $o->unit?->ukuran ? '· '.$o->unit->ukuran : '' }}
                        @if ($o->unit?->nomor_seri)<div class="text-xs text-zinc-500">SN {{ $o->unit->nomor_seri }}</div>@endif
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Kelengkapan</div>
                        {{ $o->penerimaan?->kelengkapanTeks() ?? '-' }}
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Keluhan</div>
                        <p class="whitespace-pre-line">{{ $o->keluhan }}</p>
                    </div>
                    @if ($o->penerimaan?->kondisi_fisik)
                        <div class="sm:col-span-2">
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Kondisi fisik</div>
                            <p class="whitespace-pre-line">{{ $o->penerimaan->kondisi_fisik }}</p>
                        </div>
                    @endif
                    @if ($o->penerimaan?->catatan_kerusakan)
                        <div class="sm:col-span-2">
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Catatan kerusakan</div>
                            <p class="whitespace-pre-line">{{ $o->penerimaan->catatan_kerusakan }}</p>
                        </div>
                    @endif
                    @if ($o->catatan)
                        <div class="sm:col-span-2">
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Catatan internal</div>
                            <p class="whitespace-pre-line">{{ $o->catatan }}</p>
                        </div>
                    @endif
                </div>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Estimasi & persetujuan</flux:heading>
                <livewire:order.estimasi-order :order-id="$o->id" :key="'est-'.$o->id" />
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Rincian biaya</flux:heading>
                <livewire:order.item-order :order-id="$o->id" :key="'item-'.$o->id" />
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Foto</flux:heading>
                <livewire:order.foto-order :order-id="$o->id" :key="'foto-'.$o->id" />
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading size="lg">Lokasi simpan</flux:heading>
                @can('update', $o)
                    <form wire:submit="simpanLokasi" class="flex items-end gap-2">
                        <div class="flex-1"><flux:input wire:model="lokasi_simpan" placeholder="Rak A-3" aria-label="Lokasi simpan" /></div>
                        <flux:button type="submit">Simpan</flux:button>
                    </form>
                    <flux:error name="penerimaan" />
                @else
                    <div>{{ $o->penerimaan?->lokasi_simpan ?: '-' }}</div>
                @endcan
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">Teknisi</flux:heading>
                @if ($bisaAturTeknisi)
                    <form wire:submit="simpanPelaksana" class="space-y-2">
                        @forelse ($pilihanTeknisi as $tk)
                            <div class="flex items-center justify-between gap-2" wire:key="pel-{{ $tk->id }}">
                                <span class="text-sm">{{ $tk->nama }}</span>
                                <div class="w-36">
                                    <flux:select wire:model="pelaksana.{{ $tk->id }}" size="sm" aria-label="Peran {{ $tk->nama }}">
                                        <flux:select.option value="">Tidak ikut</flux:select.option>
                                        <flux:select.option value="ketua">Penanggung jawab</flux:select.option>
                                        <flux:select.option value="anggota">Membantu</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-zinc-500">Belum ada teknisi bagian bengkel di cabang ini.</div>
                        @endforelse
                        <flux:error name="teknisi" />
                        <flux:error name="tim_id" />
                        @if ($pilihanTeknisi->isNotEmpty())
                            <flux:button type="submit" size="sm" class="w-full">Simpan teknisi</flux:button>
                        @endif
                    </form>
                @else
                    @forelse ($o->teknisi as $ot)
                        <div class="text-sm">{{ $ot->teknisi?->nama }} <span class="text-zinc-500">· {{ $ot->peran === App\Enums\PeranTim::Ketua ? 'penanggung jawab' : 'membantu' }}</span></div>
                    @empty
                        <div class="text-sm text-zinc-500">Belum ada teknisi.</div>
                    @endforelse
                @endif
            </flux:card>

            <flux:card class="space-y-2 text-sm">
                <flux:heading size="lg">Waktu</flux:heading>
                <div>Diterima: {{ tgl($o->created_at, true) }}</div>
                <div>Selesai: {{ $o->selesai_at ? tgl($o->selesai_at, true) : '-' }}</div>
                <div>{{ $o->status === App\Enums\StatusOrder::Dikembalikan ? 'Dikembalikan' : 'Diambil' }}: {{ $o->diambil_at ? tgl($o->diambil_at, true) : '-' }}</div>
            </flux:card>

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
</div>
