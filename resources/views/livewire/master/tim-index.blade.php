<div>
    <x-master.toolbar judul="Tim" deskripsi="Tim lapangan (normalnya 2 orang). Anggota yang dikeluarkan tetap tercatat sebagai riwayat." :boleh-tambah="auth()->user()->can('create', App\Models\Tim::class)" label-tambah="Tambah tim" />

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Nama tim</flux:table.column>
            <flux:table.column>Anggota aktif</flux:table.column>
            @if (auth()->user()->isOwner())
                <flux:table.column class="max-md:hidden">Cabang</flux:table.column>
            @endif
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $t)
                <flux:table.row :key="$t->id">
                    <flux:table.cell class="font-medium">{{ $t->nama_tim }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        @forelse ($t->anggotaAktif as $a)
                            <span class="mr-2 inline-flex items-center gap-1">
                                {{ $a->teknisi?->nama }}
                                @if ($a->peran === App\Enums\PeranTim::Ketua)
                                    <flux:badge size="sm" color="amber" inset="top bottom">Ketua</flux:badge>
                                @endif
                            </span>
                        @empty
                            <span class="text-zinc-500">Belum ada</span>
                        @endforelse
                    </flux:table.cell>
                    @if (auth()->user()->isOwner())
                        <flux:table.cell class="max-md:hidden">{{ $t->cabang?->nama }}</flux:table.cell>
                    @endif
                    <flux:table.cell><x-status-aktif :aktif="$t->aktif" /></flux:table.cell>
                    <flux:table.cell>
                        <x-master.aksi :model="$t" label-hapus="Hapus tim {{ $t->nama_tim }}?">
                            <flux:menu.item icon="user-plus" wire:click="kelolaAnggota({{ $t->id }})">Kelola anggota</flux:menu.item>
                        </x-master.aksi>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">Belum ada data.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Form tim --}}
    <flux:modal name="form-master" class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-5">
            <flux:heading size="lg">{{ $editId ? 'Ubah tim' : 'Tambah tim' }}</flux:heading>

            @if (auth()->user()->isOwner() && ! $editId)
                <flux:select wire:model="cabang_id" label="Cabang">
                    <flux:select.option value="">-- Pilih cabang --</flux:select.option>
                    @foreach ($pilihanCabang as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="nama_tim" label="Nama tim" placeholder="Tim A" required />
            <flux:switch wire:model="aktif" label="Aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Kelola anggota --}}
    <flux:modal name="anggota-tim" class="w-full max-w-xl">
        @if ($timDikelola)
            <div class="space-y-5">
                <flux:heading size="lg">Anggota {{ $timDikelola->nama_tim }}</flux:heading>

                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($timDikelola->anggotaAktif as $a)
                        <div class="flex items-center justify-between gap-3 px-3 py-2" wire:key="anggota-{{ $a->id }}">
                            <div>
                                <div class="font-medium">{{ $a->teknisi?->nama }}</div>
                                <div class="text-xs text-zinc-500">{{ $a->peran->label() }} · sejak {{ tgl($a->tgl_mulai) }}</div>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="user-minus"
                                wire:click="keluarkanAnggota({{ $a->id }})"
                                wire:confirm="Keluarkan {{ $a->teknisi?->nama }} dari tim?">Keluarkan</flux:button>
                        </div>
                    @empty
                        <div class="px-3 py-4 text-center text-sm text-zinc-500">Belum ada anggota aktif.</div>
                    @endforelse
                </div>

                <form wire:submit="tambahAnggota" class="space-y-4">
                    <flux:heading>Tambah anggota</flux:heading>
                    <flux:select wire:model="teknisi_id" label="Teknisi">
                        <flux:select.option value="">-- Pilih teknisi --</flux:select.option>
                        @foreach ($pilihanTeknisi as $tk)
                            <flux:select.option value="{{ $tk->id }}">{{ $tk->nama }} ({{ $tk->bagian->label() }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="peran" label="Peran">
                            @foreach ($pilihanPeran as $nilai => $label)
                                <flux:select.option value="{{ $nilai }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="tgl_mulai" label="Tanggal mulai" type="date" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary" icon="user-plus">Tambah</flux:button>
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>
</div>
