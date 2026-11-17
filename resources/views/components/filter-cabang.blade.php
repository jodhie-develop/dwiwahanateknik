@php
    $daftarCabang = \App\Models\Cabang::query()->orderBy('nama')->get(['id', 'nama']);
    $terpilih = \App\Support\KonteksCabang::filterOwner();
@endphp

<form method="POST" action="{{ route('filter-cabang') }}" x-data>
    @csrf
    <flux:select name="cabang_id" label="Lihat cabang" size="sm" x-on:change="$el.closest('form').submit()">
        <flux:select.option value="">Semua cabang</flux:select.option>
        @foreach ($daftarCabang as $c)
            <flux:select.option value="{{ $c->id }}" :selected="$terpilih === $c->id">{{ $c->nama }}</flux:select.option>
        @endforeach
    </flux:select>
</form>
