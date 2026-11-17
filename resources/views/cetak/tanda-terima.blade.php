@extends('cetak.layout', ['judul' => 'Tanda Terima '.$o->no_order])

@section('gaya')
    .rangkap { height: 148.5mm; padding: 9mm 12mm; position: relative; display: flex; flex-direction: column; }
    .rangkap + .rangkap { border-top: 1px dashed #71717a; }
    .kepala { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 3mm; }
    .toko { font-size: 13pt; font-weight: bold; }
    .kecil { font-size: 8.5pt; color: #3f3f46; }
    .nomor { text-align: right; }
    .nomor .no { font-family: 'Courier New', monospace; font-size: 15pt; font-weight: bold; }
    .salinan { position: absolute; top: 4mm; right: 12mm; font-size: 7.5pt; color: #71717a; letter-spacing: .1em; }
    table { width: 100%; border-collapse: collapse; margin-top: 3mm; }
    td { vertical-align: top; padding: 1.2mm 0; font-size: 10pt; }
    td.l { width: 33mm; color: #3f3f46; }
    .catatan { margin-top: auto; font-size: 8pt; color: #3f3f46; line-height: 1.35; }
    .ttd { display: flex; justify-content: space-between; margin-top: 4mm; font-size: 9pt; }
    .ttd div { width: 55mm; text-align: center; }
    .ttd .garis { margin-top: 14mm; border-top: 1px solid #111; padding-top: 1mm; }
@endsection

@section('isi')
    <div class="lembar">
        @foreach (['UNTUK PELANGGAN', 'ARSIP TOKO'] as $salinan)
            <div class="rangkap">
                <div class="salinan">{{ $salinan }}</div>
                <div class="kepala">
                    <div>
                        <div class="toko">{{ config('app.name') }}</div>
                        <div class="kecil">{{ $o->cabang?->nama }}{{ $o->cabang?->alamat ? ' · '.$o->cabang->alamat : '' }}</div>
                        @if ($o->cabang?->telp)<div class="kecil">Telp {{ $o->cabang->telp }}</div>@endif
                    </div>
                    <div class="nomor">
                        <div class="kecil">TANDA TERIMA SERVIS</div>
                        <div class="no">{{ $o->no_order }}</div>
                        <div class="kecil">{{ tgl($o->created_at, true) }}</div>
                    </div>
                </div>

                <table>
                    <tr><td class="l">Pelanggan</td><td><strong>{{ $o->pelanggan?->nama }}</strong> · {{ $o->pelanggan?->hp ?: '-' }}</td></tr>
                    <tr><td class="l">Barang</td><td>{{ $o->unit?->jenisPerangkat?->nama }} {{ trim($o->unit?->merk.' '.$o->unit?->model) }} {{ $o->unit?->ukuran ? '· '.$o->unit->ukuran : '' }} {{ $o->unit?->nomor_seri ? '· SN '.$o->unit->nomor_seri : '' }}</td></tr>
                    <tr><td class="l">Keluhan</td><td>{{ $o->keluhan }}</td></tr>
                    <tr><td class="l">Kelengkapan</td><td>{{ $o->penerimaan?->kelengkapanTeks() ?? '-' }}</td></tr>
                    <tr><td class="l">Kondisi fisik</td><td>{{ $o->penerimaan?->kondisi_fisik ?: '-' }}</td></tr>
                    @if ($o->penerimaan?->catatan_kerusakan)
                        <tr><td class="l">Catatan</td><td>{{ $o->penerimaan->catatan_kerusakan }}</td></tr>
                    @endif
                    @if ($biayaCek = $o->item->firstWhere('jenis', App\Enums\JenisItem::Biaya))
                        <tr><td class="l">{{ $biayaCek->nama }}</td><td>{{ rupiah($biayaCek->subtotal) }} (tetap ditagih walaupun batal diperbaiki)</td></tr>
                    @endif
                </table>

                <div class="catatan">
                    Simpan tanda terima ini dan tunjukkan saat mengambil barang. Estimasi biaya akan dikonfirmasi sebelum perbaikan dikerjakan.
                    Barang yang tidak diambil lebih dari {{ App\Support\PengaturanBengkel::batasHariAmbil($o->cabang_id) }} hari setelah selesai akan kami hubungi; kerusakan di luar keluhan di atas bukan tanggung jawab kami.
                </div>

                <div class="ttd">
                    <div>Pelanggan<div class="garis">{{ $o->pelanggan?->nama }}</div></div>
                    <div>Penerima<div class="garis">{{ $o->penerimaan?->penerima?->nama ?? $o->pembuat?->nama }}</div></div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
