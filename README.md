# Bakup

**Bakup** adalah aplikasi manajemen backup database MySQL yang dirancang untuk membantu developer, system administrator, dan perusahaan dalam melakukan backup, restore, monitoring, serta penjadwalan backup secara otomatis dengan antarmuka yang modern dan mudah digunakan.

Aplikasi ini dibangun sebagai dashboard web berbasis Laravel dengan UI gelap (dark mode), Alpine.js, dan Tailwind CSS. Engine backup menggunakan [Spatie Laravel Backup](https://github.com/spatie/laravel-backup) dengan penyesuaian khusus untuk lingkungan Windows/Laragon.

---

## Fitur Utama

### Koneksi Database
- Kelola banyak koneksi MySQL (host, port, database, kredensial)
- Uji koneksi langsung dari form sebelum disimpan

### Backup Profile
- Backup **database**, **folder**, atau keduanya dalam satu profil
- Pilih tabel yang di-**exclude** dari dump
- Opsi **stored procedures** (`--routines`) dan **views**
- Kompresi: tanpa kompresi, GZIP dump, atau ZIP (disarankan)
- **Penjadwalan otomatis**: manual, hourly, harian, mingguan, bulanan, atau custom cron
- **Retention policy**: simpan N backup terakhir, atau hapus yang lebih lama dari X hari
- Jalankan backup manual dengan modal progress real-time

### Storage Destinations
- **Local** — penyimpanan di server aplikasi
- **SFTP** — server remote via SFTP
- **S3 Compatible** — Amazon S3, Cloudflare R2, Wasabi, MinIO, dll.
- Uji koneksi storage sebelum disimpan

### Backup History & Monitoring
- Riwayat setiap eksekusi backup (sukses/gagal, ukuran, durasi)
- Unduh arsip backup dari history
- Retry backup yang gagal
- Dashboard overview: statistik, grafik aktivitas 30 hari, backup terjadwal berikutnya

### Notifikasi
- **Email** (SMTP) saat backup sukses atau gagal
- **WhatsApp** via API pihak ketiga (Fonnte, WATI, dll.)

### Keamanan & Audit
- Autentikasi login dengan role **Administrator** (Spatie Permission)
- Activity log untuk perubahan konfigurasi (Spatie Activity Log)

---

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.2+, Laravel 12 |
| Backup Engine | Spatie Laravel Backup 9 |
| Frontend | Blade, Alpine.js 3, Tailwind CSS 4, Vite 7 |
| Queue & Cache | Redis |
| Testing | Pest PHP 3 |

---

## Persyaratan

- PHP 8.2+ dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `json`, `redis`
- Composer 2.x
- Node.js 20+ & npm
- MySQL 5.7+ / MariaDB 10.3+
- Redis (session, cache, queue)
- `mysqldump` di PATH server (atau konfigurasi path khusus untuk Windows/Laragon)

---

## Instalasi

```bash
git clone <repository-url> bakup
cd bakup

composer setup
```

Perintah `composer setup` akan menjalankan: `composer install`, membuat `.env`, generate key, migrate, `npm install`, dan `npm run build`.

Atau langkah manual:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

---

## Konfigurasi

Salin `.env.example` ke `.env` lalu sesuaikan:

```env
APP_NAME="Bakup"
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=backup_manager
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

### Variabel khusus backup (Windows/Laragon)

```env
# Path mysqldump. Kosongkan untuk auto-detect (Laragon).
BACKUP_MYSQLDUMP_PATH=
BACKUP_MYSQLDUMP_AUTO_DETECT=true

# Socket MySQL lokal. Kosongkan untuk auto-detect dari my.ini.
BACKUP_MYSQL_SOCKET=
BACKUP_MYSQL_SOCKET_AUTO_DETECT=true

# Fallback dump via PHP PDO jika mysqldump gagal di Windows
BACKUP_MYSQL_DUMP_PHP_FALLBACK=true

BACKUP_GZIP_PATH=
BACKUP_GZIP_AUTO_DETECT=true
BACKUP_GZIP_ENABLED=false
```

---

## Menjalankan Aplikasi

### Development

```bash
composer dev
```

Menjalankan secara bersamaan: `php artisan serve`, queue worker, log tail (`pail`), dan Vite dev server.

Atau terpisah:

```bash
php artisan serve
php artisan queue:work redis --tries=3 --timeout=630
npm run dev
```

### Production

Pastikan scheduler Laravel berjalan (cron) dan queue worker aktif:

```bash
# Cron — jalankan setiap menit
* * * * * cd /path/to/bakup && php artisan schedule:run >> /dev/null 2>&1
```

Untuk Linux dengan Supervisor, gunakan skrip bawaan:

```bash
bash deploy/install-supervisor.sh /path/to/bakup
```

Scheduler menjalankan:
- `backup:process` — setiap menit (backup terjadwal)
- `backup:cleanup-retention` — setiap hari pukul 04:00

---

## Akun Default (Seeder)

| Email | Password | Role |
|-------|----------|------|
| `admin@backupmanager.test` | `password` | Administrator |
| `admin@local.test` | `Admin123!` | Administrator |

> Ganti password segera setelah instalasi di lingkungan production.

---

## Testing

```bash
composer test
# atau
php artisan test
```

---

## Restore Backup

Saat ini Bakup mendukung **unduh file backup** dari halaman Backup History. File arsip (ZIP/SQL) dapat digunakan untuk restore manual ke MySQL menggunakan `mysql` CLI atau alat database favorit Anda.

Fitur restore terintegrasi dari antarmuka web direncanakan sebagai pengembangan selanjutnya.

---

## Struktur Modul

```
Dashboard              → Ringkasan statistik & aktivitas backup
Database Connections   → Koneksi MySQL sumber backup
Backup Profiles        → Konfigurasi backup, schedule, retention
Storage Destinations   → Target penyimpanan (Local/SFTP/S3)
Backup History         → Riwayat, unduh, retry
Notifications          → Channel email & WhatsApp
```

---

## Lisensi

Proyek ini menggunakan lisensi [MIT](https://opensource.org/licenses/MIT).
