<div>
    <x-master.toolbar judul="Pengguna" deskripsi="Akun login aplikasi, termasuk teknisi. Data teknisi (bagian, HP, foto) diisi di sini." :boleh-tambah="auth()->user()->can('create', App\Models\User::class)" label-tambah="Tambah pengguna">
        <div class="sm:w-48">
            <flux:select wire:model.live="filterRole">
                <flux:select.option value="">Semua role</flux:select.option>
                @foreach (App\Enums\Role::cases() as $r)
                    <flux:select.option value="{{ $r->value }}">{{ $r->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-master.toolbar>

    <flux:table :paginate="$daftar">
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Username</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column class="max-md:hidden">HP</flux:table.column>
            <flux:table.column class="max-md:hidden">Cabang</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($daftar as $u)
                <flux:table.row :key="$u->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            @if ($u->teknisi?->foto)
                                <img src="{{ berkas_url($u->teknisi->foto) }}" alt="" class="size-8 rounded-full object-cover" loading="lazy">
                            @else
                                <flux:avatar size="sm" :name="$u->nama" />
                            @endif
                            <div>
                                <div class="font-medium">{{ $u->nama }}</div>
                                @if ($u->email)
                                    <div class="text-xs text-zinc-500">{{ $u->email }}</div>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $u->username }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" inset="top bottom" :color="$u->isTeknisi() ? 'sky' : ($u->isOwner() ? 'amber' : 'zinc')">{{ $u->role->label() }}</flux:badge>
                        @if ($u->teknisi)
                            <div class="mt-1 text-xs text-zinc-500">{{ $u->teknisi->bagian->label() }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $u->teknisi?->hp ?: '-' }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $u->cabang?->nama ?? 'Semua cabang' }}</flux:table.cell>
                    <flux:table.cell><x-status-aktif :aktif="$u->aktif" /></flux:table.cell>
                    <flux:table.cell><x-master.aksi :model="$u" label-hapus="Hapus pengguna {{ $u->nama }}?" /></flux:table.cell>
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
            <flux:heading size="lg">{{ $editId ? 'Ubah pengguna' : 'Tambah pengguna' }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="role" label="Role">
                    @foreach ($pilihanRole as $r)
                        <flux:select.option value="{{ $r->value }}">{{ $r->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (auth()->user()->isOwner() && $role !== App\Enums\Role::Owner->value)
                    <flux:select wire:model="cabang_id" label="Cabang">
                        <flux:select.option value="">-- Pilih cabang --</flux:select.option>
                        @foreach ($pilihanCabang as $c)
                            <flux:select.option value="{{ $c->id }}">{{ $c->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <flux:input wire:model="nama" label="Nama" required />

            @if ($role === App\Enums\Role::Teknisi->value)
                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:radio.group wire:model="bagian" label="Bagian teknisi">
                        @foreach ($pilihanBagian as $nilai => $label)
                            <flux:radio value="{{ $nilai }}" label="{{ $label }}" />
                        @endforeach
                    </flux:radio.group>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="hp" label="No. HP / WA" type="tel" />
                        <flux:input wire:model="tgl_masuk" label="Tanggal masuk" type="date" />
                    </div>

                    <div class="space-y-2">
                        <flux:input type="file" wire:model="foto" label="Foto" accept="image/*" />
                        <div wire:loading wire:target="foto" class="text-sm text-zinc-500">Mengunggah...</div>
                        @if ($foto && ! $errors->has('foto'))
                            <img src="{{ $foto->temporaryUrl() }}" alt="" class="size-20 rounded-lg object-cover">
                        @elseif ($fotoLama)
                            <img src="{{ berkas_url($fotoLama) }}" alt="" class="size-20 rounded-lg object-cover">
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="username" label="Username" autocapitalize="none" required />
                <flux:input wire:model="email" label="Email (opsional)" type="email" />
            </div>

            <flux:input wire:model="password" label="{{ $editId ? 'Password baru (kosongkan jika tidak diubah)' : 'Password' }}" type="password" autocomplete="new-password" viewable />

            <flux:switch wire:model="aktif" label="Aktif (boleh login)" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="foto,simpan">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
