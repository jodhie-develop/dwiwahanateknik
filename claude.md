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

---

# Peta kode (kondisi saat ini)

Status: Fase 1, 2A (kunjungan + PWA teknisi) dan 2B (bengkel) sudah jadi. 2C (nota kunjungan cetak & share WA) dan Fase 3–5 belum.

## Perintah
PHP/Composer di mesin dev ini tidak ada di PATH (Laragon). Di Bash: `export PATH="/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:$PATH"`.
- Dev server lengkap: `composer dev` (serve + queue + pail + vite).
- Test: `./vendor/bin/pest` · satu file: `./vendor/bin/pest tests/Feature/Order/OrderServiceTest.php` · satu test: `--filter="nama test"`.
  Test memakai SQLite in-memory + disk `local` (lihat `phpunit.xml`); jangan pernah diarahkan ke Supabase.
- Gaya kode: `vendor/bin/pint` (CI menjalankan `pint --test`).
- Aset: `npm run build` lalu **commit `public/build`**. Vercel tidak membuild aset, dan CI gagal jika `public/build` tidak sama dengan hasil build terbaru. Wajib dijalankan setelah menambah class Tailwind baru di Blade.
- RLS Supabase: otomatis setelah setiap `migrate` (listener `MigrationsEnded` → `App\Support\SupabaseRls`), atau manual `php artisan supabase:rls`.

## Arsitektur
- **Livewire 4 + Flux 2, komponen berbasis class** di `app/Livewire/{Master,Order,Bengkel,Teknisi,Pengaturan}`, view di `resources/views/livewire/...`. Volt hanya untuk halaman `settings/*` dan auth.
  Jebakan: `wire:model.blur` tidak mengirim request (pakai `wire:model.live.blur`); placeholder `flux:select` jangan pakai opsi disabled; radio Flux bukan `<input>`.
- **Routing** (`routes/web.php`): area admin `role:owner,admin_cabang` (dashboard, master, order, bengkel, pengaturan); area teknisi `role:teknisi` di prefix `/teknisi` (PWA mobile). `/` mengarahkan sesuai role. Middleware `PastikanRole` (alias `role`) hanya memisahkan area; otorisasi detail lewat Policy di `app/Policies` (trait `CekPeran`). `PastikanAkunAktif` mengeluarkan user yang dinonaktifkan.
- **Scoping cabang**: trait `App\Models\Concerns\BerCabang` di semua model ber-`cabang_id` → global scope `CabangScope` (berdasarkan `App\Support\KonteksCabang`; owner memakai filter cabang di session via `FilterCabangController`) dan memaksa `cabang_id` user non-owner saat menyimpan. `OrderServis` juga memakai `JobTeknisiScope` (teknisi hanya melihat order miliknya lewat `order_teknisi`). Di test, siapkan data cabang lain dengan helper `sebagaiSistem()` (`tests/Pest.php`).
- **Logika bisnis di service, bukan di komponen**:
  - `App\Services\OrderService` — satu-satunya jalur ubah order: buat, terima bengkel, estimasi & persetujuan, `ubahStatus` (validasi + tulis `order_status_log`), tim/teknisi, item & total, check-in, foto, tanda tangan. Pelanggaran aturan dilempar sebagai `ValidationException`; order berstatus final (`StatusOrder::terkunci()`) tidak boleh diubah.
  - Aturan transisi status hanya di `App\Enums\StatusOrder::tujuanSah()`.
  - `PenomoranDokumen` — nomor per cabang per bulan (tabel `penomoran_dokumen`, insertOrIgnore + `lockForUpdate`); panggil di dalam transaksi penyimpanan dokumen. Prefix: `KJG` kunjungan, `SRV` bengkel.
  - `PembagianTim` — bagi otomatis order kunjungan tanpa tim per tanggal (rata ke tim paling sedikit job; jika semua tim sudah bergerak → tim terdekat, haversine via `App\Support\Jarak`). Aturan lengkap di docblock class.
  - `RuteTeknisi` — titik rute halaman peta teknisi.
- **Enum** di `app/Enums` memakai trait `EnumHelpers` (`values()`, `options()` untuk dropdown) dan method `label()`/`warna()` (warna badge Flux).
- **Setting**: model `Pengaturan` (key-value; nilai per cabang menimpa global `cabang_id = null`). Akses bertipe lewat class di `app/Support`, mis. `PengaturanBengkel` (biaya cek selalu ditagih bila aktif; batas hari ambil default 14).
- **Halaman master** memakai trait `App\Livewire\Concerns\KelolaMaster` (cari, filter aktif, paginasi, modal tambah/ubah, soft delete). Trait lain: `PilihPelangganUnit`, `IsiKoordinat` (koordinat dari link Google Maps, `App\Support\KoordinatMaps`).
- **Helper global** (`app/helpers.php`): `rupiah()`, `tgl()` (d/m/Y), `berkas_url()` (temporary URL bucket privat). WhatsApp link: `App\Support\WhatsApp`.
- **File**: disk default `supabase` (S3, bucket privat) — tampilkan selalu lewat `berkas_url()`. Upload Livewire ke S3 **tidak mendukung `multiple`**: unggah satu per satu dengan komponen Blade `x-unggah-foto` + hook `updated{Properti}`.
- **Cetak bengkel**: `CetakBengkelController` (tanda terima A5×2, label 3 kolom A4), view di `resources/views/cetak`.
- **Notifikasi**: Web Push (`laravel-notification-channels/webpush`, `JobBaruDitugaskan`) dikirim via `KirimNotifikasi::aman()` agar kegagalan push tidak menggagalkan aksi. Service worker `public/sw.js`, manifest `public/manifest.webmanifest`. Di Windows lokal butuh env `OPENSSL_CONF=<php>/extras/ssl/openssl.cnf`.
- **PostgreSQL**: `App\Database\PostgresConnection` (didaftarkan di `AppServiceProvider`) mengirim boolean sebagai `'true'/'false'` saat `DB_EMULATE_PREPARES=true` (transaction pooler). `Model::shouldBeStrict()` aktif di non-produksi — hindari lazy loading.

## Deploy
- **Vercel** (`vercel.json`, region `sin1`, runtime `vercel-php`): semua request non-statis → `api/index.php`, yang mengarahkan cache bootstrap/view ke `/tmp` dan log ke stderr. Tidak ada build di Vercel (aset dari `public/build` yang di-commit).
- **Cron Vercel**: `GET /cron/bersihkan-upload-sementara` (hapus `livewire-tmp` > 24 jam di bucket), dilindungi header `Authorization: Bearer <CRON_SECRET>` (`config('services.cron.secret')`).
- CI GitHub Actions (`.github/workflows`): `tests.yml` (build aset + cek `public/build` + Pest) dan `lint.yml` (Pint).
