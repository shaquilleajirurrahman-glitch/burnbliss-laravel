# Burn & Bliss — Website E-Commerce Basque Burnt Cheesecake

Toko online **Burn & Bliss** untuk memesan Basque burnt cheesecake (Original, Oreo, Matcha, Brownie) dengan sistem **pre-order**.
Pelanggan memesan **tanpa akun** (cukup nama, nomor HP, alamat), membayar via transfer/QRIS (unggah bukti) atau tunai, lalu
melacak status pesanannya. Tim internal (Admin, Koordinator, Staf/Kasir) memverifikasi pembayaran, menjalankan alur produksi,
mengatur kuota harian, mengelola produk & pengguna, serta melihat laporan penjualan.

> Tugas Besar Pemrograman Web — **Kelompok 4**, S1 Teknologi Informasi, Telkom University Jakarta, 2026.

| Nama | NIM | Peran |
|---|---|---|
| Shaquille Ajirur Rahman | 103062400117 | Project Manager / Lead |
| Daniswara Rafi Pandora | 103062400031 | Backend Developer |
| Aurellia Fira Artanti | 103062430009 | Web Designer / Frontend |
| Vallencia Febriana | 103062400108 | QA Tester / UX |

---

## Daftar Isi

1. [Pemenuhan Ketentuan Tugas](#pemenuhan-ketentuan-tugas)
2. [Prasyarat Sistem](#prasyarat-sistem)
3. [Cara Instalasi](#cara-instalasi)
4. [Akun Demo](#akun-demo)
5. [Fitur Utama](#fitur-utama)
6. [Arsitektur & Teknologi](#arsitektur--teknologi)
7. [Struktur Folder](#struktur-folder)
8. [Pengujian](#pengujian)
9. [Catatan & Batasan](#catatan--batasan)

---

## Pemenuhan Ketentuan Tugas

| # | Ketentuan | Implementasi di proyek ini |
|---|---|---|
| 1 | **Topik bebas** | E-commerce Basque burnt cheesecake **Burn & Bliss**: katalog, keranjang, checkout pre-order, pembayaran, lacak pesanan, dan panel internal. |
| 2 | **Database + CRUD** | MySQL 8.4 dengan 8 tabel utama — `users`, `products`, `product_variants`, `daily_quotas`, `orders`, `order_items`, `payments`, `order_status_logs` (migration di `database/migrations`). CRUD lengkap lewat antarmuka:<br>• **Produk & Varian** — tambah, lihat, ubah, hapus (*soft delete*) → `/internal/produk`<br>• **Kuota Harian** — tambah, lihat, ubah, hapus → `/internal/kuota`<br>• **Pengguna** — tambah, lihat, ubah, hapus (*soft delete*) → `/internal/pengguna`<br>• **Pesanan & Pembayaran** — dibuat saat checkout, dilihat di panel & halaman lacak, diubah lewat verifikasi/alur status → `/internal/pesanan`<br>• **Keranjang** (session) — tambah, ubah jumlah, hapus item → `/cart` |
| 3 | **Minimal 3 level pengguna** | Tiga level akun internal yang **hierarkis** (`App\Enums\UserRole::level()`):<br>• **Level 3 — Admin**: akses penuh (produk, pengguna, verifikasi, laporan, kuota).<br>• **Level 2 — Koordinator**: verifikasi pembayaran, kelola pesanan & kuota, dashboard & laporan.<br>• **Level 1 — Staf/Kasir**: antrean produksi, ubah status produksi, terima pembayaran tunai.<br>Atasan mewarisi akses bawahan. Pelanggan memesan sebagai **tamu** (tanpa akun). Hak akses ditegakkan oleh `RoleMiddleware` dan *Policy* (`OrderPolicy`, `ProductPolicy`, `DailyQuotaPolicy`, `UserPolicy`). |
| 4 | **Materi Pemrograman Web** | • **HTML** — template Blade di `resources/views`.<br>• **CSS** — `public/css/burnbliss.css` (palet maroon `#771b19`, timeline, micro-interaction).<br>• **Bootstrap 5.3** — grid, card, table, form, modal (bukti bayar), toast, dropdown, progress bar.<br>• **JavaScript / jQuery 3.7** — `public/js/*.js` (keranjang, checkout, panel pesanan, laporan, produk, kuota, lacak).<br>• **AJAX / JSON** — tambah/ubah/hapus keranjang, filter & cari pesanan, verifikasi bayar & ubah status, filter laporan, ubah/hapus kuota, lacak pesanan.<br>• **PHP 8.5** — runtime di container Laravel Sail.<br>• **Laravel 13** — MVC, Eloquent, migration & seeder, Form Request, Policy, middleware, rate limiting, storage.<br>• **MySQL 8.4** — database utama (container Sail). |

---

## Prasyarat Sistem

Aplikasi dijalankan dengan **Laravel Sail** (Docker). Tidak perlu memasang PHP, Composer, MySQL, maupun XAMPP di komputer.

- **Windows**: Docker Desktop dengan **WSL 2**. Jalankan semua perintah dari terminal distro WSL (mis. Ubuntu) dan simpan proyek
  di filesystem Linux (`~/...`), **bukan** di `/mnt/c/...` (jauh lebih lambat). Aktifkan *WSL Integration* untuk distro tersebut di
  Docker Desktop → Settings → Resources.
- **macOS**: Docker Desktop.
- **Linux**: Docker Engine + plugin Docker Compose v2.
- **Git**.
- Koneksi internet saat pertama kali (unduh image Docker; Bootstrap, jQuery, dan font dimuat dari CDN).
- Port **80** (aplikasi), **3306** (MySQL), dan **8025** (Mailpit) belum dipakai aplikasi lain. Jika bentrok, atur `APP_PORT`,
  `FORWARD_DB_PORT`, atau `FORWARD_MAILPIT_DASHBOARD_PORT` di `.env`.

---

## Cara Instalasi

```bash
# 1. Clone repository
git clone <url-repository> burnbliss-laravel
cd burnbliss-laravel

# 2. Salin konfigurasi (sudah disiapkan untuk MySQL di Sail)
cp .env.example .env

# 3. Install dependensi PHP lewat container Composer (tidak perlu PHP/Composer di komputer)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 4. Nyalakan container (pertama kali: build image beberapa menit)
./vendor/bin/sail up -d

# 5. Buat application key
./vendor/bin/sail artisan key:generate

# 6. Buat tabel + data awal (3 akun demo, produk & 4 varian beserta fotonya)
./vendor/bin/sail artisan migrate --seed

# 7. Tautkan storage agar foto produk tampil
./vendor/bin/sail artisan storage:link
```

Buka **http://localhost** (katalog pelanggan) dan **http://localhost/login** (panel internal).

> **Tips**
> - Jika langkah 6 gagal dengan *Connection refused*, MySQL masih menyala — tunggu ±20 detik lalu ulangi.
> - Agar lebih singkat: `alias sail='./vendor/bin/sail'`, lalu cukup `sail up -d`, `sail artisan ...`.
> - Reset database ke kondisi awal: `./vendor/bin/sail artisan migrate:fresh --seed`.
> - Matikan container: `./vendor/bin/sail stop` (data tetap) atau `./vendor/bin/sail down -v` (hapus juga volume database).
> - Tidak perlu `npm install`/`npm run build`: aset frontend memakai CDN dan file statis di `public/`.

### Konfigurasi toko (opsional)

Nilai berikut ada di `.env` (lihat `config/burnbliss.php`):

| Variabel | Default | Keterangan |
|---|---|---|
| `BB_BANK_NAMA`, `BB_BANK_NOREK`, `BB_BANK_ATAS_NAMA` | `BCA`, `0000000000`, `Burn & Bliss Bakery` | Rekening tujuan transfer (nomor rekening masih **contoh**). |
| `BB_KUOTA_DEFAULT` | `30` | Kuota produksi (pcs) per tanggal yang belum diatur admin. |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu tanggal pre-order & laporan. |

---

## Akun Demo

Halaman login: **http://localhost/login** (khusus tim internal; tidak ada registrasi publik).

| Role | Email | Password | Halaman awal setelah login |
|---|---|---|---|
| Admin (Level 3) | `admin@burnbliss.com` | `password` | `/admin/dashboard` |
| Koordinator (Level 2) | `koordinator@burnbliss.com` | `password` | `/koordinator/dashboard` |
| Staf/Kasir (Level 1) | `staf@burnbliss.com` | `password` | `/staf/dashboard` |

**Pelanggan tidak memerlukan akun** — buka http://localhost, masukkan produk ke keranjang, lalu checkout.

> Password demo hanya untuk lingkungan lokal. Ganti lewat menu **Kelola → Pengguna** sebelum dipakai sungguhan.

---

## Fitur Utama

### Pelanggan (tamu, tanpa login)
- **Katalog** produk & varian dengan harga, foto, dan status stok — `/`.
- **Keranjang** berbasis session dengan **AJAX** (tambah, ubah jumlah, hapus) dan badge navbar yang ter-update tanpa reload — `/cart`.
- **Checkout pre-order**: nama, nomor HP, ambil sendiri / diantar (alamat), tanggal ambil (H+1 s.d. H+14, Minggu tutup),
  metode bayar **Transfer / QRIS / Tunai**. Stok dan **kuota harian** dicek dan dikurangi secara atomik — `/checkout`.
- **Pembayaran**: instruksi rekening atau QRIS, **unggah bukti** (JPG/PNG maks. 2 MB); pesanan tunai langsung dikonfirmasi.
- **Lacak pesanan** dengan kode pesanan + nomor HP, menampilkan **timeline status** vertikal (AJAX) — `/lacak`.

### Staf/Kasir (Level 1)
- **Antrean produksi** (Dikonfirmasi → Diproses → Siap → Selesai) dengan tombol ubah status via AJAX — `/internal/pesanan`.
- **Tandai lunas** pembayaran tunai saat serah terima.
- Tidak dapat melihat data keuangan maupun bukti transfer.

### Koordinator (Level 2) — semua akses Staf, ditambah:
- **Semua pesanan**: filter status & pencarian (AJAX), detail pesanan.
- **Verifikasi pembayaran**: lihat bukti di modal, **Terima** (pesanan dikonfirmasi) atau **Tolak** (pelanggan unggah ulang).
- **Dashboard** omzet & statistik — `/internal/dashboard`.
- **Laporan penjualan** per periode & varian (AJAX) + **ekspor CSV** — `/internal/laporan`.
- **Kuota harian**: atur & ubah kuota produksi per tanggal — `/internal/kuota`.

### Admin (Level 3) — semua akses Koordinator, ditambah:
- **CRUD produk & varian** dengan unggah foto — `/internal/produk`.
- **CRUD pengguna** & role (tidak bisa menghapus/menurunkan role akun sendiri) — `/internal/pengguna`.
- Hapus kuota harian.

### Alur status pesanan

```
Transfer/QRIS : Menunggu Pembayaran → Menunggu Verifikasi → Dikonfirmasi → Diproses → Siap Diambil/Dikirim → Selesai
                                       └─(bukti ditolak)→ kembali ke Menunggu Pembayaran
Tunai         : Dikonfirmasi → Diproses → Siap Diambil/Dikirim → Selesai   (dibayar saat serah terima)
```

Status tidak bisa loncat atau mundur sembarangan (aturan di `App\Enums\OrderStatus::nextAllowed()`).

---

## Arsitektur & Teknologi

Proyek mengikuti pola **MVC** Laravel secara ketat:

| Lapisan | Lokasi | Isi |
|---|---|---|
| **Model** | `app/Models` | Eloquent: `Product`, `ProductVariant`, `DailyQuota`, `Order`, `OrderItem`, `Payment`, `OrderStatusLog`, `User` beserta relasi, *cast* enum, dan *soft delete*. |
| **View** | `resources/views` | Blade + Bootstrap 5 (`layouts/shop` untuk toko, `layouts/app` untuk panel internal). |
| **Controller** | `app/Http/Controllers` (`Admin/` untuk panel) | Menerima request dan mengembalikan view/JSON; tipis, logika bisnis didelegasikan. |
| Validasi | `app/Http/Requests` | *Form Request* dengan pesan berbahasa Indonesia. |
| Otorisasi | `app/Policies`, `app/Http/Middleware` | *Policy* per entitas, `RoleMiddleware` (`role:admin,koordinator`), `EnsureOrderAccess`. |
| Logika bisnis | `app/Services` | `CartService`, `CheckoutService`, `OrderWorkflow`, `OrderAccess`, `OrderTimeline`, `SalesReport`, `ProductManager`. |
| Enum | `app/Enums` | `UserRole`, `OrderStatus`, `PaymentStatus`, `PaymentMethod`, `ShippingMethod`. |
| Frontend | `public/css`, `public/js` | CSS kustom + jQuery per halaman (AJAX/JSON). |

| Teknologi | Versi |
|---|---|
| Laravel | 13.38 |
| PHP | 8.5 (container Sail) |
| MySQL | 8.4 (container Sail) |
| Bootstrap / Bootstrap Icons | 5.3.3 / 1.11.3 (CDN, dengan SRI) |
| jQuery | 3.7.1 (CDN, dengan SRI) |
| Laravel Sail (Docker) | `laravel.test`, `mysql`, `mailpit` |
| PHPUnit | lewat `sail artisan test` |

### Keamanan yang diterapkan
- Password di-*hash* (bcrypt), CSRF di semua form, validasi server di setiap input.
- *Rate limit*: login 5/menit, checkout 5/menit per form, lacak pesanan 10/menit.
- Bukti transfer disimpan di **disk privat** (`storage/app/private`) dan hanya bisa dibuka Admin/Koordinator.
- Halaman pesanan tamu hanya bisa dibuka oleh session pemesan atau setelah verifikasi nomor HP; data publik disamarkan.
- Transaksi database + *row lock* pada checkout, verifikasi, dan perubahan status (aman dari klik bersamaan).
- Ekspor CSV dilindungi dari *formula injection*.

---

## Struktur Folder

```
burnbliss-laravel/
├── app/
│   ├── Enums/           # UserRole, OrderStatus, PaymentStatus, PaymentMethod, ShippingMethod
│   ├── Http/            # Controllers (Admin/), Middleware, Requests (Admin/)
│   ├── Models/          # Model Eloquent
│   ├── Policies/        # Otorisasi per entitas
│   ├── Providers/       # Rate limiter & view composer
│   ├── Services/        # Logika bisnis
│   └── Support/         # Helper: Rupiah, NomorHp, PreOrder, Csv
├── bootstrap/           # app.php (registrasi middleware & alias)
├── config/              # Termasuk burnbliss.php (pengaturan toko)
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/         # Akun demo, produk, foto contoh (seeders/images)
├── public/
│   ├── css/             # burnbliss.css
│   ├── images/          # QRIS
│   └── js/              # burnbliss.js, cart.js, checkout.js, track.js, admin-*.js
├── resources/views/     # Blade: catalog, cart, checkout, track, admin, auth, layouts, partials
├── routes/web.php       # Semua route web
├── storage/
├── tests/
│   ├── Feature/         # Termasuk Feature/Admin
│   └── Unit/
├── .env.example         # Konfigurasi siap pakai untuk Sail
├── compose.yaml         # Service Docker (Sail)
└── README.md
```

---

## Pengujian

```bash
./vendor/bin/sail artisan test
```

**134 test / 957 assertion** lulus, mencakup:

| File | Cakupan |
|---|---|
| `tests/Feature/AuthRoleTest.php` | Login internal, hierarki role, matriks hak akses, rate limit login. |
| `tests/Feature/CartTest.php` | Katalog, keranjang AJAX/JSON, validasi stok. |
| `tests/Feature/CheckoutTest.php` | Checkout tamu, kuota & stok, unggah bukti, tunai, akses pesanan. |
| `tests/Feature/TrackOrderTest.php` | Lacak pesanan, timeline, 404 & rate limit. |
| `tests/Feature/Admin/OrderManagementTest.php` | Verifikasi pembayaran, alur produksi, kasir tunai, RBAC. |
| `tests/Feature/Admin/DashboardReportTest.php` | Angka dashboard, filter laporan, ekspor CSV. |
| `tests/Feature/Admin/ProductManagementTest.php` | CRUD produk & varian, foto, *soft delete*. |
| `tests/Feature/Admin/DailyQuotaManagementTest.php` | CRUD kuota & integrasi dengan checkout. |
| `tests/Feature/Admin/UserManagementTest.php` | CRUD pengguna, password hash, email unik, *soft delete*. |

Test memakai database terpisah `testing` (dibuat otomatis oleh container MySQL Sail).

---

## Catatan & Batasan

- **Rekening tujuan** (`BB_BANK_NOREK`) masih contoh; isi dengan rekening asli sebelum digunakan.
- **Foto produk** Oreo, Matcha, dan Brownie berasal dari prototipe lama dan belum tentu milik sendiri — ganti dengan foto produk
  sendiri sebelum dipublikasikan.
- Belum ada **notifikasi** email/WhatsApp otomatis dan **pembatalan otomatis** pesanan yang tidak dibayar (stok & kuota tetap tertahan
  sampai pesanan diproses).
- Halaman yang memakai AJAX (keranjang, panel pesanan, laporan, lacak) membutuhkan JavaScript aktif dan koneksi internet (CDN).
- `CLAUDE.md` dan folder `.claude/` berisi aturan kerja tim untuk asisten AI selama pengembangan; tidak dibutuhkan untuk menjalankan aplikasi.
