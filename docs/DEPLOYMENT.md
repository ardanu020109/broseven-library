# Panduan Deployment Production

## 1. Persyaratan server

- PHP 8.3 atau lebih baru dengan ekstensi: `bcmath, ctype, curl, dom, fileinfo, gd, iconv, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, xmlwriter, zip`
- MySQL 8.0.16+ atau MariaDB 10.6+ (dibutuhkan untuk CHECK constraint stok)
- Composer 2, web server (Nginx atau Apache) dengan **document root = folder `public`**, dan HTTPS

## 2. Konfigurasi `.env` production

```env
APP_NAME="Nama Perpustakaan"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://perpustakaan.contoh.id
APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta        # sesuaikan: Asia/Makassar atau Asia/Jayapura

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=broseven_library
DB_USERNAME=<pengguna khusus, bukan root>
DB_PASSWORD=<password kuat>

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
LOG_CHANNEL=daily
LOG_LEVEL=warning

MAIL_MAILER=smtp                 # wajib agar fitur lupa password mengirim email sungguhan
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@perpustakaan.contoh.id
```

Jangan pernah mengunggah `.env` ke repository. `APP_DEBUG=true` di production membocorkan data sensitif.

## 3. Langkah deploy pertama

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan library:install         # membuat Super Admin pertama (password kuat)
php artisan storage:link
php artisan optimize                # cache config, route, event, view
```

Atur izin tulis untuk `storage` dan `bootstrap/cache` bagi pengguna web server, dan pastikan folder `storage/fonts` ada (DomPDF).

## 4. Penjadwal (wajib)

Tambahkan satu entri cron agar peminjaman yang lewat jatuh tempo ditandai otomatis setiap hari:

```cron
* * * * * cd /path/ke/broseven-library && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Di balik reverse proxy atau load balancer

Jika HTTPS diakhiri di proxy, daftarkan proxy tepercaya di `bootstrap/app.php`:
`$middleware->trustProxies(at: '*')` (atau daftar IP proxy), agar `isSecure()` dan URL QR benar.

## 6. Pembaruan versi

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

## 7. Backup

- Database harian: `mysqldump --single-transaction broseven_library | gzip > backup-$(date +%F).sql.gz`
- Folder `storage/app/public` (cover, foto, logo) ikut dicadangkan.
- Simpan cadangan di lokasi terpisah dan uji pemulihan secara berkala.

## 8. Checklist sebelum go-live

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS aktif
- [ ] Akun development tidak ada; Super Admin dibuat dengan `library:install`
- [ ] Profil Perpustakaan dilengkapi (nama, alamat, logo, aturan, denda)
- [ ] Email keluar diuji (lupa password)
- [ ] Cron penjadwal berjalan (`php artisan schedule:list`)
- [ ] Backup otomatis aktif dan pernah diuji pulihkan
- [ ] `php artisan optimize` berhasil tanpa error
