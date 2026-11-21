{{-- Ringkasan bengkel --}}
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg" class="font-display !font-semibold">Barang di bengkel</flux:heading>
        @if ($belumDiambilLewat > 0)
            <flux:button size="sm" icon="exclamation-triangle" class="text-amber-600" :href="route('bengkel.index', ['tampil' => 'belum_diambil'])" wire:navigate>
                {{ $belumDiambilLewat }} belum diambil > {{ $batasHariAmbil }} hari
            </flux:button>
        @endif
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach ($ringkasBengkel as $r)
            <a href="{{ route('bengkel.index', ['tampil' => $r['status']->value]) }}" wire:navigate wire:key="bkl-{{ $r['status']->value }}"
               @class([
                   'rounded-xl border p-3 transition-colors',
                   'border-sky-200 bg-sky-50 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/30 dark:hover:bg-sky-950/50' => $r['status'] === App\Enums\StatusOrder::Diterima,
                   'border-blue-200 bg-blue-50 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:hover:bg-blue-950/50' => $r['status'] === App\Enums\StatusOrder::Diagnosa,
                   'border-amber-200 bg-amber-50 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/30 dark:hover:bg-amber-950/50' => $r['status'] === App\Enums\StatusOrder::MenungguKonfirmasi,
                   'border-indigo-200 bg-indigo-50 hover:bg-indigo-100 dark:border-indigo-900 dark:bg-indigo-950/30 dark:hover:bg-indigo-950/50' => $r['status'] === App\Enums\StatusOrder::Dikerjakan,
                   'border-orange-200 bg-orange-50 hover:bg-orange-100 dark:border-orange-900 dark:bg-orange-950/30 dark:hover:bg-orange-950/50' => $r['status'] === App\Enums\StatusOrder::MenungguSparepart,
                   'border-green-200 bg-green-50 hover:bg-green-100 dark:border-green-900 dark:bg-green-950/30 dark:hover:bg-green-950/50' => $r['status'] === App\Enums\StatusOrder::Selesai,
                   'border-red-200 bg-red-50 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/30 dark:hover:bg-red-950/50' => $r['status'] === App\Enums\StatusOrder::Batal,
               ])>
                <div class="text-2xl font-semibold">{{ $r['jumlah'] }}</div>
                <div class="text-xs text-zinc-500">{{ $r['label'] }}</div>
            </a>
        @endforeach
    </div>
</div>
