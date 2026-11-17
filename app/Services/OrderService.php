<?php

namespace App\Services;

use App\Enums\JenisItem;
use App\Enums\KategoriFoto;
use App\Enums\MediaPersetujuan;
use App\Enums\PeranTim;
use App\Enums\StatusBayar;
use App\Enums\StatusEstimasi;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\Barang;
use App\Models\Cabang;
use App\Models\Layanan;
use App\Models\OrderEstimasi;
use App\Models\OrderFoto;
use App\Models\OrderItem;
use App\Models\OrderServis;
use App\Models\Teknisi;
use App\Models\Tim;
use App\Models\User;
use App\Support\PengaturanBengkel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan penting pada order lewat sini: pembuatan, status, tim/teknisi, item, foto, tanda tangan.
 * Kesalahan aturan bisnis dilempar sebagai ValidationException agar tampil rapi di form.
 */
class OrderService
{
    public function __construct(private PenomoranDokumen $penomoran) {}

    /**
     * @param  array<string, mixed>  $data  kolom order (cabang_id, tipe, pelanggan_id, unit_pelanggan_id, keluhan, tgl_jadwal, jam_janji, catatan)
     * @param  list<array{layanan_id: int, qty: float|int}>  $layanan  item layanan awal (opsional)
     */
    public function buat(array $data, User $oleh, array $layanan = []): OrderServis
    {
        return DB::transaction(function () use ($data, $oleh, $layanan) {
            $tipe = TipeOrder::from($data['tipe']);
            $cabang = Cabang::query()->findOrFail($data['cabang_id']);

            $order = new OrderServis($data);
            $order->forceFill([
                'cabang_id' => $cabang->id,
                'no_order' => $this->penomoran->berikutnya($cabang, $tipe->prefixNomor()),
                'status' => $tipe->statusAwal(),
                'status_bayar' => StatusBayar::Belum,
                'dibuat_oleh' => $oleh->id,
            ])->save();

            $this->tulisLog($order, null, $order->status, $oleh, 'Order dibuat');

            foreach ($layanan as $baris) {
                $this->tambahItem($order, JenisItem::Layanan, (int) $baris['layanan_id'], (float) $baris['qty'], null, $oleh);
            }

            return $order->refresh();
        });
    }

    /**
     * Terima barang servis bengkel: order + tanda terima + biaya cek (jika aktif) + teknisi + foto penerimaan.
     *
     * @param  array<string, mixed>  $data  kolom order (cabang_id, pelanggan_id, unit_pelanggan_id, keluhan, catatan)
     * @param  array<string, mixed>  $penerimaan  kondisi_fisik, kelengkapan (array), catatan_kerusakan, lokasi_simpan
     * @param  array<int, string>  $teknisi  teknisi_id => peran (opsional)
     * @param  list<UploadedFile>  $foto  foto penerimaan
     */
    public function terimaBengkel(array $data, array $penerimaan, User $oleh, array $teknisi = [], array $foto = []): OrderServis
    {
        $order = DB::transaction(function () use ($data, $penerimaan, $oleh, $teknisi) {
            $order = $this->buat([
                ...$data,
                'tipe' => TipeOrder::Bengkel->value,
                'tgl_jadwal' => today()->toDateString(), // tanggal barang diterima
                'jam_janji' => null,
            ], $oleh);

            $order->penerimaan()->create([...$penerimaan, 'diterima_oleh' => $oleh->id]);

            // Keputusan pemilik: biaya cek selalu ditagih (jika diaktifkan di pengaturan cabang).
            if (($biayaCek = PengaturanBengkel::biayaCek($order->cabang_id)) > 0) {
                $order->item()->create([
                    'jenis' => JenisItem::Biaya,
                    'nama' => 'Biaya cek',
                    'qty' => 1,
                    'harga' => $biayaCek,
                    'subtotal' => $biayaCek,
                    'dibuat_oleh' => $oleh->id,
                ]);
                $this->hitungUlangTotal($order);
            }

            if ($teknisi !== []) {
                $this->aturTeknisi($order, $teknisi, $oleh);
            }

            return $order;
        });

        foreach ($foto as $file) {
            $this->tambahFoto($order, $file, KategoriFoto::Penerimaan, $oleh);
        }

        return $order->refresh();
    }

    /** @param  array<string, mixed>  $data */
    public function ubahPenerimaan(OrderServis $order, array $data): void
    {
        if (in_array($order->status, [StatusOrder::Diambil, StatusOrder::Dikembalikan], true)) {
            throw ValidationException::withMessages(['penerimaan' => 'Barang sudah diserahkan ke pelanggan.']);
        }

        $order->penerimaan()->updateOrCreate(['order_id' => $order->id], $data);
    }

    // ---------------- Estimasi & persetujuan (bengkel) ----------------

    /** Teknisi/admin mengajukan estimasi biaya; order otomatis menunggu konfirmasi pelanggan. */
    public function ajukanEstimasi(OrderServis $order, int $nominal, ?string $catatan, User $oleh): OrderEstimasi
    {
        return DB::transaction(function () use ($order, $nominal, $catatan, $oleh) {
            $order = $this->kunci($order);

            if ($order->tipe !== TipeOrder::Bengkel || ! in_array($order->status, [StatusOrder::Diagnosa, StatusOrder::MenungguKonfirmasi], true)) {
                throw ValidationException::withMessages(['estimasi' => 'Estimasi hanya bisa diajukan saat order berstatus Diagnosa / Menunggu konfirmasi.']);
            }

            if ($nominal < 0) {
                throw ValidationException::withMessages(['estimasi' => 'Nominal estimasi tidak valid.']);
            }

            // Revisi estimasi saat masih menunggu: estimasi lama dianggap diganti (ditolak otomatis).
            $order->estimasi()->where('status', StatusEstimasi::Menunggu->value)->update([
                'status' => StatusEstimasi::Ditolak->value,
                'diputuskan_at' => now(),
                'catatan_keputusan' => 'Diganti estimasi baru',
                'dicatat_oleh' => $oleh->id,
            ]);

            $estimasi = $order->estimasi()->create([
                'nominal' => $nominal,
                'catatan' => $catatan,
                'status' => StatusEstimasi::Menunggu,
                'dibuat_oleh' => $oleh->id,
            ]);

            if ($order->status === StatusOrder::Diagnosa) {
                $this->ubahStatus($order, StatusOrder::MenungguKonfirmasi, $oleh, 'Estimasi '.rupiah($nominal).' diajukan');
            } else {
                $this->tulisLog($order, $order->status, $order->status, $oleh, 'Estimasi direvisi menjadi '.rupiah($nominal));
            }

            return $estimasi;
        });
    }

    /** Catat jawaban pelanggan. Setuju → Dikerjakan; tolak → Batal (biaya cek tetap ditagih). */
    public function catatPersetujuan(OrderServis $order, bool $setuju, MediaPersetujuan $media, User $oleh, ?string $catatan = null): void
    {
        DB::transaction(function () use ($order, $setuju, $media, $oleh, $catatan) {
            $order = $this->kunci($order);
            $estimasi = $order->estimasiTerakhir()->first();

            if ($order->status !== StatusOrder::MenungguKonfirmasi || $estimasi?->status !== StatusEstimasi::Menunggu) {
                throw ValidationException::withMessages(['estimasi' => 'Tidak ada estimasi yang menunggu jawaban pelanggan.']);
            }

            $estimasi->update([
                'status' => $setuju ? StatusEstimasi::Disetujui : StatusEstimasi::Ditolak,
                'media' => $media,
                'diputuskan_at' => now(),
                'catatan_keputusan' => $catatan,
                'dicatat_oleh' => $oleh->id,
            ]);

            $keterangan = ($setuju ? 'Estimasi disetujui' : 'Estimasi ditolak').' pelanggan via '.$media->label()
                .($catatan ? ": {$catatan}" : '');

            $this->ubahStatus($order, $setuju ? StatusOrder::Dikerjakan : StatusOrder::Batal, $oleh, $keterangan);
        });
    }

    /**
     * Ubah data dasar order kunjungan yang masih Dijadwalkan.
     * Jika tanggal berubah, penugasan tim dilepas (antrian hari lain berbeda) dan perlu dibagi ulang.
     *
     * @param  array<string, mixed>  $kolom
     */
    public function ubahData(OrderServis $order, array $kolom, User $oleh): OrderServis
    {
        return DB::transaction(function () use ($order, $kolom, $oleh) {
            $order = $this->kunci($order);

            if ($order->status !== StatusOrder::Dijadwalkan) {
                throw ValidationException::withMessages(['order' => 'Order hanya bisa diubah selama berstatus Dijadwalkan.']);
            }

            unset($kolom['cabang_id'], $kolom['tipe']);
            $tanggalLama = $order->tgl_jadwal->toDateString();
            $order->fill($kolom);

            $catatan = 'Data order diubah';
            if ($order->tim_id && $order->tgl_jadwal->toDateString() !== $tanggalLama) {
                $order->forceFill(['tim_id' => null, 'ditugaskan_at' => null]);
                $order->teknisi()->delete();
                $catatan .= '; tanggal berubah sehingga tim dilepas dan perlu dibagi ulang';
            }

            $order->save();
            $this->tulisLog($order, $order->status, $order->status, $oleh, $catatan);

            return $order;
        });
    }

    // ---------------- Status ----------------

    public function ubahStatus(OrderServis $order, StatusOrder $ke, ?User $oleh, ?string $catatan = null): OrderServis
    {
        return DB::transaction(function () use ($order, $ke, $oleh, $catatan) {
            $order = $this->kunci($order);
            $dari = $order->status;

            if (! $dari->bolehKe($ke, $order->tipe)) {
                throw ValidationException::withMessages([
                    'status' => "Status tidak bisa diubah dari \"{$dari->label()}\" ke \"{$ke->label()}\".",
                ]);
            }

            if ($ke === StatusOrder::Batal && blank($catatan)) {
                throw ValidationException::withMessages(['status' => 'Alasan pembatalan wajib diisi.']);
            }

            if ($order->tipe === TipeOrder::Kunjungan && $ke === StatusOrder::Berangkat) {
                if (! $order->tim_id || ! $order->teknisi()->exists()) {
                    throw ValidationException::withMessages(['status' => 'Order belum punya tim/teknisi pelaksana.']);
                }
            }

            if ($order->tipe === TipeOrder::Bengkel) {
                $this->jagaTransisiBengkel($order, $ke);
            }

            $order->status = $ke;

            if ($ke === StatusOrder::Selesai) {
                $order->selesai_at = now();
            }

            if (in_array($ke, [StatusOrder::Diambil, StatusOrder::Dikembalikan], true)) {
                $order->diambil_at = now();
            }

            $order->save();
            $this->tulisLog($order, $dari, $ke, $oleh, $catatan);

            return $order;
        });
    }

    // ---------------- Tim & teknisi ----------------

    /**
     * Tetapkan tim untuk order kunjungan; teknisi pelaksana diisi ulang dari anggota aktif tim.
     *
     * @return list<int> id user teknisi yang ditugaskan (untuk notifikasi)
     */
    public function tetapkanTim(OrderServis $order, Tim $tim, ?User $oleh): array
    {
        return DB::transaction(function () use ($order, $tim, $oleh) {
            $order = $this->kunci($order);
            $this->pastikanBisaAturTim($order);

            if ((int) $tim->cabang_id !== (int) $order->cabang_id || ! $tim->aktif) {
                throw ValidationException::withMessages(['tim_id' => 'Tim tidak aktif atau berasal dari cabang lain.']);
            }

            $anggota = $tim->anggotaAktif()->with(['teknisi' => fn ($q) => $q->withoutGlobalScopes()])->get()
                ->filter(fn ($a) => $a->teknisi?->aktif && ! $a->teknisi->trashed());

            if ($anggota->isEmpty()) {
                throw ValidationException::withMessages(['tim_id' => "Tim {$tim->nama_tim} belum punya anggota aktif."]);
            }

            $timLama = $order->tim_id;
            $order->forceFill(['tim_id' => $tim->id, 'ditugaskan_at' => now()])->save();

            $order->teknisi()->delete();
            foreach ($anggota as $a) {
                $order->teknisi()->create(['teknisi_id' => $a->teknisi_id, 'peran' => $a->peran]);
            }

            if ((int) $timLama !== (int) $tim->id) {
                $this->tulisLog($order, $order->status, $order->status, $oleh, "Ditugaskan ke {$tim->nama_tim}");
            }

            return $anggota->pluck('teknisi.user_id')->filter()->values()->all();
        });
    }

    /**
     * Ganti teknisi pelaksana order (mis. anggota tim berhalangan).
     *
     * @param  array<int, string>  $teknisi  teknisi_id => peran (ketua|anggota)
     */
    public function aturTeknisi(OrderServis $order, array $teknisi, ?User $oleh): void
    {
        DB::transaction(function () use ($order, $teknisi, $oleh) {
            $order = $this->kunci($order);
            $this->pastikanBisaAturTim($order);

            if ($teknisi === []) {
                throw ValidationException::withMessages(['teknisi' => 'Minimal satu teknisi pelaksana.']);
            }

            $valid = Teknisi::query()->withoutGlobalScopes()
                ->whereIn('id', array_keys($teknisi))
                ->where('cabang_id', $order->cabang_id)
                ->where('aktif', true)
                ->whereNull('deleted_at')
                ->pluck('id')->all();

            if (count($valid) !== count($teknisi)) {
                throw ValidationException::withMessages(['teknisi' => 'Ada teknisi yang tidak aktif atau dari cabang lain.']);
            }

            $order->teknisi()->delete();
            foreach ($teknisi as $id => $peran) {
                $order->teknisi()->create(['teknisi_id' => $id, 'peran' => PeranTim::from($peran)]);
            }

            $this->tulisLog($order, $order->status, $order->status, $oleh, 'Teknisi pelaksana diubah');
        });
    }

    private function pastikanBisaAturTim(OrderServis $order): void
    {
        $boleh = $order->tipe === TipeOrder::Kunjungan
            ? $order->status === StatusOrder::Dijadwalkan
            : ! $order->terkunci();

        if (! $boleh) {
            throw ValidationException::withMessages([
                'tim_id' => $order->tipe === TipeOrder::Kunjungan
                    ? 'Tim/teknisi hanya bisa diubah selama order berstatus Dijadwalkan.'
                    : 'Teknisi tidak bisa diubah pada order yang sudah selesai/batal.',
            ]);
        }
    }

    /** Syarat tambahan alur bengkel: teknisi sebelum diagnosa, estimasi sebelum konfirmasi, persetujuan sebelum dikerjakan. */
    private function jagaTransisiBengkel(OrderServis $order, StatusOrder $ke): void
    {
        $estimasi = $order->estimasiTerakhir()->first();

        $pesan = match (true) {
            $ke === StatusOrder::Diagnosa && ! $order->teknisi()->exists() => 'Pilih teknisi bengkel terlebih dahulu.',
            $ke === StatusOrder::MenungguKonfirmasi && $estimasi?->status !== StatusEstimasi::Menunggu => 'Ajukan estimasi biaya terlebih dahulu.',
            $order->status === StatusOrder::MenungguKonfirmasi && $ke === StatusOrder::Dikerjakan && $estimasi?->status !== StatusEstimasi::Disetujui => 'Order belum bisa dikerjakan: estimasi belum disetujui pelanggan.',
            $ke === StatusOrder::Batal && $estimasi?->status === StatusEstimasi::Menunggu => 'Catat dulu jawaban pelanggan atas estimasi (ditolak).',
            default => null,
        };

        if ($pesan) {
            throw ValidationException::withMessages(['status' => $pesan]);
        }
    }

    // ---------------- Item ----------------

    public function tambahItem(OrderServis $order, JenisItem $jenis, int $id, float $qty, ?int $harga, ?User $oleh): OrderItem
    {
        return DB::transaction(function () use ($order, $jenis, $id, $qty, $harga, $oleh) {
            $order = $this->kunci($order);
            $this->pastikanBelumTerkunci($order);

            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Jumlah harus lebih dari 0.']);
            }

            if ($jenis === JenisItem::Layanan) {
                $sumber = Layanan::query()->aktif()->findOrFail($id);
                $hargaMaster = $sumber->hargaDiCabang($order->cabang_id) ?? 0;
            } else {
                $sumber = Barang::query()->aktif()->findOrFail($id);
                $hargaMaster = $sumber->harga_jual;
            }

            $harga ??= $hargaMaster;

            $item = $order->item()->create([
                'jenis' => $jenis,
                'layanan_id' => $jenis === JenisItem::Layanan ? $sumber->id : null,
                'barang_id' => $jenis === JenisItem::Barang ? $sumber->id : null,
                'nama' => $sumber->nama,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => (int) round($qty * $harga),
                'dibuat_oleh' => $oleh?->id,
            ]);

            $this->hitungUlangTotal($order);

            return $item;
        });
    }

    public function hapusItem(OrderServis $order, int $itemId): void
    {
        DB::transaction(function () use ($order, $itemId) {
            $order = $this->kunci($order);
            $this->pastikanBelumTerkunci($order);

            $order->item()->whereKey($itemId)->firstOrFail()->delete();
            $this->hitungUlangTotal($order);
        });
    }

    public function ubahDiskon(OrderServis $order, int $diskon): void
    {
        DB::transaction(function () use ($order, $diskon) {
            $order = $this->kunci($order);
            $this->pastikanBelumTerkunci($order);

            if ($diskon < 0 || $diskon > $order->subtotal) {
                throw ValidationException::withMessages(['diskon' => 'Diskon harus antara 0 dan subtotal.']);
            }

            $order->diskon = $diskon;
            $this->hitungUlangTotal($order);
        });
    }

    public function hitungUlangTotal(OrderServis $order): void
    {
        $order->subtotal = (int) $order->item()->sum('subtotal');
        $order->diskon = min($order->diskon, $order->subtotal);
        $order->total = max(0, $order->subtotal - $order->diskon);
        $order->save();
    }

    // ---------------- Lapangan ----------------

    public function checkin(OrderServis $order, float $lat, float $lng): void
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw ValidationException::withMessages(['checkin' => 'Koordinat GPS tidak valid.']);
        }

        $order->forceFill(['checkin_at' => now(), 'checkin_lat' => $lat, 'checkin_lng' => $lng])->save();
    }

    public function tambahFoto(OrderServis $order, UploadedFile $file, KategoriFoto $kategori, ?User $oleh): OrderFoto
    {
        $this->pastikanBelumTerkunci($order);

        $path = $file->store("order/{$order->id}");

        return $order->foto()->create(['kategori' => $kategori, 'path' => $path, 'diunggah_oleh' => $oleh?->id]);
    }

    public function hapusFoto(OrderServis $order, int $fotoId): void
    {
        $this->pastikanBelumTerkunci($order);

        $foto = $order->foto()->whereKey($fotoId)->firstOrFail();
        Storage::delete($foto->path);
        $foto->delete();
    }

    /** @param  string  $dataUrl  "data:image/png;base64,..." dari canvas tanda tangan */
    public function simpanTandaTangan(OrderServis $order, string $dataUrl): void
    {
        $this->pastikanBelumTerkunci($order);

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw ValidationException::withMessages(['tanda_tangan' => 'Format tanda tangan tidak valid.']);
        }

        $png = base64_decode($m[1], true);

        if ($png === false || strlen($png) > 1024 * 1024 || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['tanda_tangan' => 'Gambar tanda tangan tidak valid.']);
        }

        $path = "order/{$order->id}/tanda-tangan-".now()->format('YmdHis').'.png';
        Storage::put($path, $png);

        if ($order->tanda_tangan) {
            Storage::delete($order->tanda_tangan);
        }

        $order->forceFill(['tanda_tangan' => $path])->save();
    }

    // ---------------- Internal ----------------

    /** Ambil ulang order dengan kunci baris (tanpa scope, karena hak akses sudah dicek pemanggil). */
    private function kunci(OrderServis $order): OrderServis
    {
        return OrderServis::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
    }

    private function pastikanBelumTerkunci(OrderServis $order): void
    {
        if ($order->terkunci()) {
            throw ValidationException::withMessages([
                'order' => "Order berstatus {$order->status->label()} sudah tidak bisa diubah.",
            ]);
        }
    }

    private function tulisLog(OrderServis $order, ?StatusOrder $dari, StatusOrder $ke, ?User $oleh, ?string $catatan): void
    {
        $order->log()->create([
            'dari_status' => $dari,
            'ke_status' => $ke,
            'user_id' => $oleh?->id,
            'catatan' => $catatan,
        ]);
    }
}
