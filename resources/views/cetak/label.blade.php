@extends('cetak.layout', ['judul' => 'Label '.$o->no_order])

@section('gaya')
    .grid { display: grid; grid-template-columns: repeat(3, 70mm); grid-auto-rows: 37mm; padding: 0; }
    .label { border: 1px dashed #a1a1aa; padding: 3mm 4mm; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
    .label .toko { font-size: 7pt; color: #52525b; text-transform: uppercase; letter-spacing: .05em; }
    .label .no { font-family: 'Courier New', monospace; font-size: 13.5pt; font-weight: bold; line-height: 1.1; }
    .label .nama { font-size: 10pt; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .info { font-size: 8pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@endsection

@section('alat')
    <form method="GET" style="display:flex;gap:6px;align-items:center;margin-left:auto">
        <label for="jumlah">Jumlah label</label>
        <input id="jumlah" type="number" name="jumlah" min="1" max="24" value="{{ $jumlah }}" style="width:60px;padding:5px">
        <button type="submit">Ubah</button>
    </form>
@endsection

@section('isi')
    <div class="lembar">
        <div class="grid">
            @for ($i = 0; $i < $jumlah; $i++)
                <div class="label">
                    <div class="toko">{{ config('app.name') }} · {{ $o->cabang?->kode }}</div>
                    <div class="no">{{ $o->no_order }}</div>
                    <div class="nama">{{ $o->pelanggan?->nama }}</div>
                    <div class="info">{{ $o->unit?->jenisPerangkat?->nama }} {{ $o->unit?->merk }} · terima {{ tgl($o->created_at) }}</div>
                </div>
            @endfor
        </div>
    </div>
@endsection
