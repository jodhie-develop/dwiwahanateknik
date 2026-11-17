{{--
    Tombol ambil/pilih foto. Beberapa foto boleh dipilih sekaligus, tetapi diunggah SATU PER SATU ke
    properti Livewire tunggal ($model), karena upload langsung ke S3/Supabase Storage tidak mendukung
    upload "multiple" Livewire. Komponen Livewire menangani tiap file di hook updated{Model}.
--}}
@props(['model', 'label' => 'Ambil / pilih foto'])

<label {{ $attributes->class('flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 px-4 py-4 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700/40') }}
    x-data="{
        total: 0,
        ke: 0,
        async unggah(input) {
            const daftar = [...input.files];
            this.total = daftar.length;
            for (const [i, file] of daftar.entries()) {
                this.ke = i + 1;
                await new Promise((selesai) => $wire.upload(@js($model), file, () => selesai(), () => selesai()));
            }
            this.total = 0;
            input.value = '';
        },
    }">
    <flux:icon.camera />
    <span x-show="total === 0">{{ $label }}</span>
    <span x-show="total > 0" x-cloak>Mengunggah <span x-text="ke"></span>/<span x-text="total"></span>...</span>
    <input type="file" accept="image/*" capture="environment" multiple class="hidden" x-on:change="unggah($event.target)">
</label>
