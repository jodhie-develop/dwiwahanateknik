<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('bengkel.index')" wire:navigate />
        <div>
            <flux:heading size="xl" level="1">Terima barang servis</flux:heading>
            <flux:subheading>Barang dibawa pelanggan ke cabang. Tanda terima & label bisa dicetak setelah disimpan.</flux:subheading>
        </div>
    </div>

    <form wire:submit="simpan" class="space-y-6">
        @if (auth()->user()->isOwner())
            <div class="sm:w-64">
                <flux:select wire:model.live="cabang_id" label="Cabang">
                    <flux:select.option value="">-- Pilih cabang --</flux:select.option>
                    @foreach ($pilihanCabang as $c)
                        <flux:select.option value="{{ $c->id }}">{{ $c->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        @include('livewire.order.partials.pilih-pelanggan-unit', ['judulUnit' => '2. Barang yang diservis', 'alamatWajib' => false])

        <flux:card class="space-y-4">
            <flux:heading size="lg">3. Kondisi saat diterima</flux:heading>

            <flux:textarea wire:model="keluhan" label="Keluhan pelanggan" rows="2" required placeholder="TV mati total, ada bunyi klik" />
            <flux:textarea wire:model="kondisi_fisik" label="Kondisi fisik" rows="2" placeholder="Layar tergores di pojok kanan, casing belakang retak" />

            <flux:checkbox.group wire:model="kelengkapan" label="Kelengkapan yang dibawa">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($kelengkapanUmum as $k)
                        <flux:checkbox value="{{ $k }}" label="{{ $k }}" />
                    @endforeach
                </div>
            </flux:checkbox.group>
            <flux:input wire:model="kelengkapan_lain" label="Kelengkapan lain" placeholder="Pisahkan dengan koma, mis. Bracket dinding, Baut 4 pcs" />

            <flux:textarea wire:model="catatan_kerusakan" label="Catatan kerusakan / hasil cek awal" rows="2" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="lokasi_simpan" label="Lokasi simpan" placeholder="Rak A-3" />
            </div>

            <div class="space-y-2">
                <flux:label>Foto penerimaan</flux:label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 px-4 py-4 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700/40">
                    <flux:icon.camera />
                    <span wire:loading.remove wire:target="foto">Ambil / pilih foto barang</span>
                    <span wire:loading wire:target="foto">Mengunggah...</span>
                    <input type="file" wire:model="foto" accept="image/*" capture="environment" multiple class="hidden">
                </label>
                <flux:error name="foto" />
                <flux:error name="foto.*" />
                @if ($foto)
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($foto as $i => $f)
                            @if (! $errors->has('foto.'.$i))
                                <div class="relative" wire:key="pv-{{ $i }}">
                                    <img src="{{ $f->temporaryUrl() }}" class="aspect-square w-full rounded-lg object-cover" alt="">
                                    <button type="button" wire:click="hapusFoto({{ $i }})" class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white" aria-label="Hapus">
                                        <flux:icon.x-mark variant="micro" />
                                    </button>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">4. Teknisi & catatan</flux:heading>

            @if ($pilihanTeknisi->isNotEmpty())
                <flux:checkbox.group wire:model="teknisi" label="Teknisi bengkel (boleh diisi nanti)" description="Teknisi pertama yang dicentang menjadi penanggung jawab.">
                    @foreach ($pilihanTeknisi as $t)
                        <flux:checkbox value="{{ $t->id }}" label="{{ $t->nama }}" description="{{ $t->bagian->label() }}" />
                    @endforeach
                </flux:checkbox.group>
            @else
                <flux:text class="text-sm">Belum ada teknisi bagian Bengkel / Lapangan & Bengkel di cabang ini.</flux:text>
            @endif

            <flux:textarea wire:model="catatan" label="Catatan internal" rows="2" />

            @if ($biayaCek > 0)
                <flux:callout icon="information-circle" heading="Biaya cek {{ rupiah($biayaCek) }} otomatis ditagihkan" text="Biaya cek tetap berlaku walaupun pelanggan batal memperbaiki." />
            @endif
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('bengkel.index')" wire:navigate>Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="foto,simpan">Simpan & buat tanda terima</flux:button>
        </div>
    </form>
</div>
