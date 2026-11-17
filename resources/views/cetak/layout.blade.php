<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ?? 'Cetak' }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e4e4e7; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; }
        .lembar { width: 210mm; min-height: 297mm; margin: 10mm auto; background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.2); }
        .alat { max-width: 210mm; margin: 10mm auto 0; display: flex; gap: 8px; align-items: center; font-size: 10pt; }
        .alat button, .alat a { padding: 6px 12px; border: 1px solid #a1a1aa; border-radius: 6px; background: #fff; color: #111; text-decoration: none; cursor: pointer; }
        .alat button.utama { background: #18181b; color: #fff; border-color: #18181b; }
        @media print {
            html, body { background: #fff; }
            .lembar { margin: 0; box-shadow: none; }
            .alat { display: none; }
        }
        @yield('gaya')
    </style>
</head>
<body>
    <div class="alat">
        <button class="utama" onclick="window.print()">Cetak</button>
        <a href="javascript:window.close()">Tutup</a>
        @yield('alat')
    </div>
    @yield('isi')
</body>
</html>
