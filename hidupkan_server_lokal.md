# Panduan Menyalakan Server Lokal (Docker Compose / Laravel Sail)

Cara push ke github via SSH, ganti xxx dengan keterangan yg sesuai.

```bash
git add .
git commit -m "xxx"
git push origin main
```

Dokumen ini menjelaskan cara menjalankan project di laptop menggunakan Docker Compose melalui Laravel Sail.



## 1. Masuk ke folder project

```bash
cd /home/pgun/dev/card
```

## 2. Nyalakan semua service

```bash
./vendor/bin/sail up -d
```

Service utama yang akan jalan:
- `laravel.test` (PHP + web app)
- `pgsql`
- `redis`
- `minio`
- `mailpit`

## 3. Jika port Vite bentrok (misal port 5173 sudah dipakai)

Gunakan port lain saat start:

```bash
VITE_PORT=5180 ./vendor/bin/sail up -d
```

Catatan:
- Jika perlu permanen, set `VITE_PORT` di file `.env`.

## 4. Jalankan migration database

```bash
./vendor/bin/sail artisan migrate --force
```

## 5. (Opsional) Isi data awal (seed)

```bash
./vendor/bin/sail artisan db:seed --force
```

## 6. Cek status container

```bash
./vendor/bin/sail ps
```

Pastikan `STATUS` untuk service yang dibutuhkan adalah `Up`.

## 7. Akses aplikasi

- Laravel App: `http://localhost`  
  (atau mengikuti `APP_PORT` di `.env`)
- MinIO API: `http://localhost:19000`
- MinIO Console: `http://localhost:18901`
- Mailpit: cek port yang tampil di `sail ps`

## 8. Lihat log jika ada error

```bash
./vendor/bin/sail logs -f laravel.test
```

Untuk service lain:

```bash
./vendor/bin/sail logs -f pgsql
./vendor/bin/sail logs -f minio
./vendor/bin/sail logs -f redis
```

## 9. Masuk ke shell container app

```bash
./vendor/bin/sail shell
```

Contoh perintah di dalam container:

```bash
php artisan route:list
php artisan test
```

## 10. Matikan server lokal

```bash
./vendor/bin/sail down
```

Jika ingin sekaligus hapus volume data:

```bash
./vendor/bin/sail down -v
```

Peringatan: `-v` akan menghapus data database/volume lokal container.

---

## Troubleshooting singkat

### A. Error: `Bind for 0.0.0.0:5173 failed: port is already allocated`

Solusi cepat:

```bash
VITE_PORT=5180 ./vendor/bin/sail up -d
```

### B. Error: `service "laravel.test" is not running`

Jalankan ulang service app:

```bash
./vendor/bin/sail up -d laravel.test
```

### C. Docker belum aktif

Pastikan Docker Desktop (atau Docker Engine) sudah running, lalu ulangi:

```bash
./vendor/bin/sail up -d
```
