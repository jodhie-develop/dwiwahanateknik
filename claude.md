# Instruksi Agent — Sistem Manajemen Servis Elektronik & AC

## Peran
Kamu adalah senior Laravel engineer yang membangun aplikasi manajemen usaha servis elektronik dan AC: penjadwalan teknisi lapangan, servis titip di cabang, administrasi, stok, dan keuangan. Tulis kode yang rapi, aman, mudah dirawat, dan konsisten dengan konvensi Laravel. Bahasa UI: **Bahasa Indonesia**. Nama tabel/kolom: Bahasa Indonesia, snake_case.

## Cara kerja
1. Kerjakan **per fase** (lihat bagian "Tahapan"). Sebelum mulai satu fase, tampilkan rencana singkat: migration, model, route, halaman, dan test yang akan dibuat. Tunggu persetujuan.
2. Jangan menambah fitur di luar spesifikasi. Jika ada yang ambigu atau bertentangan, **tanya dulu**, jangan menebak.
3. Setiap selesai fitur: jalankan migration, jalankan test, dan laporkan hasilnya singkat.
4. Jangan pernah mengubah/menghapus data produksi atau menjalankan `migrate:fresh` tanpa izin eksplisit.
5. Jangan menulis kredensial di kode. Semua lewat `.env`.

## Stack teknis
- **Laravel 12**, PHP 8.2+.
- **Database: Supabase (PostgreSQL)** — dipakai sebagai Postgres biasa melalui koneksi `pgsql` Laravel.
  - Skema dikelola **hanya lewat migration Laravel**, bukan dari dashboard Supabase.
  - Gunakan **session pooler (port 5432)** atau direct connection. Jika terpaksa memakai transaction pooler (port 6543), aktifkan `PDO::ATTR_EMULATE_PREPARES => true` pada config koneksi.
  - Aktifkan **RLS di semua tabel tanpa policy**, supaya tabel tidak bisa diakses lewat Supabase REST API publik (anon key). Akses data hanya melalui Laravel.
- **Auth**: auth bawaan Laravel (session) untuk panel admin; **Laravel Sanctum** untuk API jika nanti ada aplikasi mobile. Tidak memakai Supabase Auth.
- **File/foto**: **Supabase Storage** via driver `s3` Laravel (S3-compatible):
  ```
  FILESYSTEM_DISK=supabase
  # config/filesystems.php → disk 'supabase' driver s3
  # endpoint: https://<project-ref>.supabase.co/storage/v1/s3
  # use_path_style_endpoint: true
  # bucket privat, akses file lewat temporary URL
  ```
- **UI admin**: Blade + Livewire (starter kit Livewire Laravel 12), layout responsif.
- **UI teknisi**: halaman mobile-first di aplikasi yang sama (PWA), dioptimalkan untuk HP: tombol besar, upload foto dari kamera, tanda tangan di layar.
- **Test**: Pest. Minimal feature test untuk alur order, perhitungan keuangan, stok, dan scoping cabang.
- Timezone aplikasi: `Asia/Jakarta`. Format tanggal tampilan: `d/m/Y`. Mata uang Rupiah tanpa desimal.

## Konvensi data
- Primary key `bigIncrements` (id). Semua tabel pakai `timestamps`; data master pakai `softDeletes` dan kolom `aktif` (boolean).
- Nominal uang disimpan sebagai **bigInteger (rupiah)**, bukan float.
- Semua tabel transaksi wajib punya `cabang_id`.
- Nomor dokumen dibuat otomatis per cabang per bulan, contoh `SRV/SBY/2611/0001`. Pembuatan nomor harus aman dari race condition (lock/transaksi DB).
- Status pakai **PHP Enum** (backed string), bukan string bebas.
- Setiap perubahan status order dicatat di tabel log (siapa, kapan, dari status apa ke status apa, catatan).

## Multi-cabang & hak akses
Saat ini 1 cabang, tetapi sistem harus siap banyak cabang tanpa ubah struktur.
- Role: `owner` (semua cabang), `admin_cabang` (hanya cabangnya), `teknisi` (hanya job miliknya).
- Terapkan scoping cabang otomatis (global scope / policy) untuk admin_cabang dan teknisi. Owner bisa memilih filter cabang.
- Gunakan Policy/Gate Laravel untuk otorisasi, jangan cek role tersebar di view.

## Data master
1. **cabang**: kode, nama, alamat, telp, aktif.
2. **users**: nama, email/username, password, role, cabang_id (null untuk owner), aktif.
3. **teknisi**: cabang_id, user_id (untuk login), nama, hp, foto, tgl_masuk, bagian (`lapangan` / `bengkel` / `keduanya`), aktif.
4. **tim**: cabang_id, nama_tim, aktif.
   **tim_anggota**: tim_id, teknisi_id, peran (`ketua` / `anggota`), tgl_mulai, tgl_selesai. Satu tim lapangan normalnya 2 orang.
5. **jenis_perangkat**: AC, TV, kulkas, mesin cuci, dll.
6. **layanan**: jenis_perangkat_id, nama (cuci AC, isi freon, ganti panel, dll), tipe yang berlaku (`kunjungan` / `bengkel` / `keduanya`), masa_garansi_hari, interval_reminder_hari (null jika tidak ada; misal cuci AC 90 hari).
   **layanan_harga**: layanan_id, cabang_id, harga (harga bisa berbeda per cabang).
7. **pelanggan**: nama, hp/WA, alamat, catatan. Pelanggan bisa lintas cabang.
   **unit_pelanggan**: pelanggan_id, jenis_perangkat_id, merk, model, nomor_seri, ukuran/PK, lokasi (misal "kamar depan"), alamat (jika beda dengan alamat pelanggan).
8. **barang** (sparepart & bahan: freon, pipa, kapasitor, dll): kode, nama, satuan, harga_jual, aktif.
   Stok disimpan **per cabang** (lihat bagian Stok).

## Order servis
Satu tabel utama `order_servis` untuk dua tipe, dengan kolom `tipe`:
- `kunjungan` — dikerjakan tim di lokasi pelanggan (terutama AC).
- `bengkel` — barang dibawa ke cabang (TV, kulkas, mesin cuci, dll).

Kolom inti: cabang_id, no_order, tipe, pelanggan_id, unit_pelanggan_id, keluhan, tgl_jadwal, jam_jadwal (kunjungan), tim_id (kunjungan), status, subtotal, diskon, total, status_bayar, order_garansi_dari_id (nullable, merujuk order sebelumnya jika klaim garansi), catatan.

Tabel pendukung:
- **order_teknisi**: order_id, teknisi_id, peran. Ini yang mencatat siapa yang **benar-benar** mengerjakan. Saat admin memilih tim, isi otomatis dari anggota tim yang aktif; admin boleh mengganti orangnya per order. Komisi dihitung dari tabel ini, bukan dari `tim_id`.
- **order_item**: layanan atau barang, qty, harga, subtotal. Item barang mengurangi stok saat order selesai.
- **order_foto**: order_id, kategori (`sebelum`, `sesudah`, `penerimaan`, `lainnya`), path (Supabase Storage).
- **order_status_log**: lihat konvensi data.

### Alur status — kunjungan
`dijadwalkan → berangkat → dikerjakan → selesai`, dan `batal` dari status mana pun sebelum `selesai`.

### Alur status — bengkel
`diterima → diagnosa → menunggu_konfirmasi → dikerjakan ⇄ menunggu_sparepart → selesai → diambil`
Cabang batal: `menunggu_konfirmasi → batal → dikembalikan`.

Validasi transisi status di satu tempat (enum/service), tolak transisi yang tidak sah.

### Khusus servis bengkel
- **Tanda terima** (`order_penerimaan`): kondisi fisik, kelengkapan (remote, kabel, dll), catatan kerusakan, foto penerimaan, lokasi_simpan (rak/area). Bisa dicetak dan dibagikan via WhatsApp.
- **Label nomor servis** yang bisa dicetak untuk ditempel di barang.
- **Estimasi & persetujuan**: nominal estimasi, tanggal disetujui, media persetujuan (WA/telepon/langsung), dicatat oleh siapa. Order tidak boleh masuk `dikerjakan` sebelum disetujui.
- **Biaya cek / DP** (opsional, diatur di setting).
- **Barang belum diambil**: tampilkan daftar barang berstatus `selesai` yang melewati batas hari pengambilan (setting), untuk di-follow up.

### Khusus servis kunjungan
- Kalender/jadwal per tim per hari, cegah tim dijadwalkan bentrok di jam yang sama (beri peringatan).
- Halaman teknisi: daftar job hari ini, tombol buka Google Maps, ubah status, check-in dengan koordinat GPS, upload foto sebelum/sesudah, input item layanan & barang, input pembayaran yang diterima, tanda tangan pelanggan.

## Garansi
- Masa garansi dihitung dari tanggal `selesai` order + `masa_garansi_hari` layanan.
- Order klaim garansi dibuat dengan `order_garansi_dari_id`, default tanpa biaya, dan tidak dihitung sebagai pendapatan/komisi baru (kecuali admin mengubah).

## Reminder pelanggan
Daftar unit pelanggan yang sudah waktunya servis ulang berdasarkan `interval_reminder_hari` layanan terakhir. Tombol kirim WhatsApp menggunakan link `https://wa.me/<nomor>?text=<pesan>` (template pesan bisa diatur). Belum memakai WhatsApp API berbayar.

## Keuangan
- **Pembayaran**: order_id, tanggal, nominal, metode (`tunai`, `transfer`, `qris`), diterima_oleh (user/teknisi). Mendukung pembayaran sebagian. `status_bayar` order: `belum`, `sebagian`, `lunas`.
- **Setoran kas teknisi**: uang tunai yang diterima teknisi di lapangan harus disetor. Admin membuat setoran harian per teknisi/tim, sistem menampilkan total tunai yang seharusnya disetor vs yang disetor, dan selisihnya.
- **Pengeluaran**: cabang_id, tanggal, kategori (bensin, pembelian barang, operasional, dll), nominal, keterangan, bukti foto.
- **Komisi teknisi**: berdasarkan order `selesai` dan `order_teknisi`. Skema komisi diatur di setting (persentase atau nominal tetap per layanan) dan aturan pembagian dalam tim (rata, atau porsi ketua berbeda). Hasil komisi disimpan per order per teknisi agar tidak berubah jika setting diubah kemudian.
- **Laporan**: pendapatan, pengeluaran, laba per periode dan per cabang; piutang; rekap per teknisi/tim; rekap per layanan. Bisa diekspor ke Excel.

## Stok
- **stok_cabang**: barang_id, cabang_id, qty.
- **mutasi_stok**: barang_id, cabang_id, tanggal, tipe (`masuk`, `keluar_order`, `penyesuaian`, `transfer_masuk`, `transfer_keluar`), qty, referensi (order/pembelian), user.
- Stok berkurang otomatis saat order selesai; dikembalikan jika order dibatalkan setelah stok terpotong.
- Semua perubahan stok wajib lewat service yang menulis `mutasi_stok` dalam transaksi DB. Jangan update qty langsung di tempat lain.
- Peringatan stok minimum per barang per cabang.

## Dashboard
- Owner: ringkasan semua cabang (order hari ini, pendapatan bulan ini, piutang, barang bengkel menumpuk).
- Admin cabang: jadwal tim hari ini, order bengkel per status, setoran yang belum masuk, reminder pelanggan.
- Teknisi: job hari ini.

## Tahapan
**Fase 1 — Fondasi**: setup Laravel 12 + koneksi Supabase + Storage, auth, role & scoping cabang, semua data master (cabang, user, teknisi, tim, jenis perangkat, layanan & harga, pelanggan & unit, barang).

**Fase 2 — Order (MVP inti)**: order kunjungan & bengkel, alur status, order_teknisi, tanda terima, estimasi & persetujuan, foto, halaman teknisi mobile, nota/tanda terima cetak & share WA.

**Fase 3 — Keuangan**: pembayaran, setoran kas teknisi, pengeluaran, laporan dasar.

**Fase 4 — Stok**: stok per cabang, mutasi, pemotongan otomatis dari order.

**Fase 5 — Lanjutan**: komisi, garansi, reminder pelanggan, dashboard lengkap, ekspor Excel.

## Hal yang belum diputuskan (buat bisa diatur di setting, atau tanyakan saat sampai di fase terkait)
- Susunan tim tetap atau sering dirotasi.
- Skema dan pembagian komisi dalam tim.
- Ada/tidaknya biaya cek dan DP untuk servis bengkel.
- Batas hari pengambilan barang bengkel.
