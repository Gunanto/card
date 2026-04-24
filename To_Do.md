# To_Do - Lanjutan Besok

Dokumen ini menjadi panduan kerja lanjutan dari hasil hari ini (`blueprint.md`, `erd.md`, dan `database/migrations_draft`).

Tanggal referensi: **Kamis, 23 April 2026**  
Target lanjut: **Jumat, 24 April 2026**

Update terakhir: **Jumat, 24 April 2026 (malam, lanjutan)**  
Progress utama: prioritas **1-6 selesai**, prioritas **7-10 sudah jalan (MVP dasar)**, modul **Import CSV/Excel** dan **PDF A4 2x5 batch** sudah masuk kode.

## 1) Status Saat Ini

Sudah selesai:
- Blueprint produk dan arsitektur: [blueprint.md](/home/pgun/dev/card/blueprint.md)
- ERD final + standar layout A4 2x5: [erd.md](/home/pgun/dev/card/erd.md)
- Draft migration Laravel: [database/migrations_draft/README.md](/home/pgun/dev/card/database/migrations_draft/README.md)

Catatan:
- Validasi sintaks PHP sudah dijalankan via container (`php -l`) dan lolos.
- Migrate + seed PostgreSQL sudah berhasil via Docker network internal.

## 2) Prioritas Besok (Urutan Eksekusi)

1. Setup skeleton project Laravel 13 + Inertia + Vue.
2. Pindahkan draft migration ke `database/migrations/`.
3. Jalankan migrate di PostgreSQL.
4. Buat model + relasi Eloquent.
5. Buat seeder awal (`card_types`, admin default, institution dummy).
6. Implement auth + role gate (`admin`, `guru`).
7. Implement modul `institutions`, `students`, `classes` (CRUD dasar). `Selesai di kode`
8. Implement upload media ke MinIO (`media_assets`). `Selesai di kode`
9. Implement `card_templates` + simpan `config_json` dan `print_layout_json`. `Selesai di kode`
10. Implement generate batch dasar + simpan `generated_cards`. `Selesai di kode`
11. Implement modul `users` (CRUD admin/guru + role guard admin). `Selesai di kode`

## 3) Checklist Teknis Detail

### A. Environment & Infra
- [ ] Pastikan tool tersedia: `php`, `composer`, `node`, `npm/pnpm`, `psql`.
- [x] Siapkan `docker-compose` untuk `postgres`, `redis`, `minio`.
- [x] Buat bucket MinIO: `card-app` (private).
- [x] Set `.env` Laravel untuk koneksi PostgreSQL, Redis, dan MinIO.

### B. Database
- [x] Copy semua file dari `database/migrations_draft/` ke `database/migrations/`.
- [x] Cek urutan timestamp migration tetap konsisten.
- [x] Jalankan `php artisan migrate`.
- [x] Verifikasi constraint penting:
- `students (institution_id, student_code)` unique.
- FK branding di `institutions` mengarah ke `media_assets`.
- Unique `generated_cards (batch_id, student_id)`.

### C. Seeder
- [x] Seeder `card_types`: `exam`, `student`, `library`, `event`, `custom`.
- [x] Seeder admin awal.
- [x] Seeder 1 instansi dummy + 1 guru dummy + 2 kelas + sample student.

### D. Backend Domain
- [x] Buat model:
- `Institution`, `User`, `Classroom` (atau `SchoolClass`), `Student`
- `CardType`, `CardTemplate`, `MediaAsset`
- `Import`, `GenerateBatch`, `GeneratedCard`, `ActivityLog`
- [x] Definisikan relasi Eloquent antar model.
- [x] Implement policy minimum:
- Admin full access.
- Guru terbatas ke `institution_id` sendiri.
- [x] Modul `users` (admin only) untuk CRUD akun admin/guru.

### E. Media & MinIO
- [x] Implement endpoint upload file ke MinIO.
- [x] Simpan metadata ke `media_assets`.
- [x] Validasi file:
- foto: `jpg/jpeg/png/webp`
- stempel/ttd: prefer `png`
- ukuran file & dimensi minimal
- [x] Implement generate presigned URL untuk preview/download.

### F. Template & Print Layout
- [x] CRUD `card_templates`.
- [x] Simpan default `print_layout_json` A4 2x5.
- [ ] Implement parser `config_json` elemen kartu (saat ini belum dipakai untuk render aktual).
- [ ] Siapkan 1 template default yang memuat:
- foto siswa
- logo instansi
- stempel instansi
- tanda tangan pimpinan
- nama dan jabatan pimpinan

### G. Generate Kartu (MVP)
- [x] Endpoint membuat `generate_batches`.
- [x] Queue job untuk proses per-student.
- [x] Simpan output file ke MinIO + catat di `media_assets`.
- [x] Update status `generated_cards` dan `generate_batches`.
- [x] Simpan `asset_snapshot_json` saat render.
- [ ] Render final sesuai template/layout A4 2x5 (saat ini masih placeholder SVG/PDF sederhana).

### H. QA Minimum
- [ ] Uji generate 1 kartu.
- [ ] Uji generate batch 10 kartu (A4 2x5).
- [ ] Uji skenario foto hilang (fallback).
- [ ] Uji hak akses guru lintas instansi (harus ditolak).

### I. Import Data (Blueprint 4.5)
- [x] Endpoint import CSV/Excel (`/imports/students`) + mapping kolom opsional.
- [x] Proses async import + laporan error per baris (tabel `imports`).
- [x] UI monitoring hasil import (`/imports`).

### J. Audit & Monitoring (Blueprint 4.9)
- [ ] Logging aktivitas sensitif ke `activity_logs` (login, update template, generate, import).
- [ ] Monitoring queue depth / failed jobs di dashboard.

### K. Export & Print Quality (Blueprint 4.8 + 3.1)
- [x] Output PDF A4 2x5 per batch dengan engine render nyata (`dompdf`) via endpoint download batch.
- [ ] Export massal ZIP.
- [ ] Verifikasi akurasi ukuran print `mm` vs hasil PDF fisik.

## 7) Audit Gap vs ERD + Blueprint

Status saat ini dibagi menjadi `DONE`, `PARTIAL`, `TODO`.

### DONE
- Auth login/logout + role gate (`admin`, `guru`) + active middleware.
- User management (`/users`) untuk admin.
- CRUD `institutions`, `classrooms`, `students`, `card_templates`.
- Upload media ke MinIO + metadata `media_assets` + presigned URL.
- Generate batch dasar + queue per-student + simpan `generated_cards`.
- Struktur tabel inti ERD sudah ada di migration (`imports`, `activity_logs`, `template_elements` termasuk skema).
- Penambahan field profil yang diminta terbaru:
  - `institutions`: `npsn`, `village`, `district`, `regency`, `province`, `postal_code`, `website`
  - `students`: `nis`, `nisn`, `nik`, `npwp`, `religion`, `village`, `district`, `regency`, `province`, `mobile_phone`, `motto`

### PARTIAL
- Template engine:
  - `config_json` dan `print_layout_json` tersimpan, tetapi render job belum membaca elemen JSON secara penuh.
- Generate output:
  - Sudah tersimpan ke storage + DB, tetapi PDF masih placeholder, belum kualitas cetak final A4 2x5.
- QA:
  - Build frontend, migrate, syntax check sudah lolos.
  - Skenario QA bisnis end-to-end belum lengkap.

### TODO (Belum Dikerjakan)
- Import CSV/Excel + mapping + error report per row.
- Aktivitas audit log real-time ke tabel `activity_logs`.
- Monitoring job queue (failed/retry/duration) di dashboard.
- Export ZIP massal.
- Opsi QR/Barcode (Blueprint fase lanjut).
- Kontrak endpoint API formal (request/response) untuk tiap modul.

## 4) Definisi Selesai (MVP Internal)

MVP dianggap selesai jika:
- Admin bisa setup branding instansi (logo/stempel/ttd).
- Guru bisa input/import siswa dan upload foto.
- Guru bisa memilih template dan generate kartu massal.
- Hasil bisa diunduh sebagai PDF dengan layout A4 2x5.
- Semua media tersimpan di MinIO dan metadata tercatat di DB.

## 5) Risiko yang Perlu Dipantau

- Akurasi ukuran cetak mm vs rendering browser/PDF.
- Performa generate batch besar tanpa queue tuning.
- Konsistensi akses file private MinIO (presigned URL expiry).
- Konflik naming model `Class` (gunakan `Classroom`/`SchoolClass` di code).

## 6) Rekomendasi Start Besok (90 Menit Pertama)

1. Buat project Laravel + setup env + docker services.
2. Jalankan migrate + seed basic.
3. Implement upload MinIO paling dulu (fondasi untuk branding/foto/template).
4. Lanjut CRUD `students` dan `card_templates`.
