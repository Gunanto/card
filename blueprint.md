# Blueprint Aplikasi Pembuatan Kartu Berbasis Web

## 1. Ringkasan

Dokumen ini mendeskripsikan blueprint teknis untuk aplikasi web pembuatan kartu (kartu ujian, kartu pelajar/siswa, kartu anggota perpustakaan, dan kartu lain yang serupa) dengan peran `admin` dan `guru`.

Target utama:
- Mendukung input data tunggal dan massal.
- Mendukung upload/import foto identitas.
- Mendukung template kartu fleksibel.
- Mendukung komponen branding instansi:
- `logo instansi`
- `stempel instansi`
- `tanda tangan pimpinan`
- Mendukung generate dan export kartu massal.

---

## 2. Scope Fungsional

### 2.1 Jenis Kartu
- Kartu ujian
- Kartu siswa/pelajar
- Kartu anggota perpustakaan
- Kartu kegiatan (event/extracurricular)
- Kartu custom sesuai template

### 2.2 Peran Pengguna
- `Admin`
- Kelola user guru.
- Kelola template.
- Kelola profil/branding instansi.
- Kelola data master (kelas, sekolah, jenis kartu).
- Kelola import data massal.
- Lihat audit log dan monitoring job.

- `Guru`
- Input/edit data peserta sesuai scope.
- Import data peserta.
- Upload/import foto.
- Pilih template dan generate kartu.
- Export PDF/ZIP hasil kartu.

---

## 3. Arsitektur Teknologi (Rekomendasi)

### 3.1 Stack
- Backend: `Laravel 13`
- Frontend: `Vue 3 + Inertia.js + Tailwind CSS`
- Database: `PostgreSQL`
- Cache & Queue: `Redis`
- Object Storage: `MinIO` (S3-compatible)
- Rendering Engine: `Spatie Browsershot` (Headless Chrome) untuk PDF kualitas tinggi
- Worker: Laravel queue worker terpisah

### 3.2 Alasan Pemilihan MinIO
- Cocok untuk aset file besar dan banyak (foto, template background, hasil PDF).
- S3-compatible, mudah migrasi ke AWS S3/DO Spaces jika dibutuhkan.
- Storage tidak menumpuk di disk aplikasi.
- Mendukung presigned URL dan pola akses private yang aman.

### 3.3 Komponen Runtime
- `web` (Laravel + Inertia)
- `worker` (queue processor)
- `postgres`
- `redis`
- `minio`
- opsional: `nginx` reverse proxy

---

## 4. Modul Aplikasi

### 4.1 Authentication & Authorization
- Login/logout.
- Role-based access (`admin`, `guru`).
- Policy/Gate untuk pembatasan akses data.

### 4.2 Dashboard
- Ringkasan jumlah peserta, template, batch generate.
- Statistik foto belum lengkap.
- Monitoring job import/generate.

### 4.3 User Management
- CRUD user guru.
- Aktivasi/non-aktivasi akun.
- Scope akses (per instansi/kelas jika diperlukan).

### 4.4 Data Peserta
- CRUD data peserta.
- Pencarian/filter (nama, nomor, kelas, jenis kartu).
- Validasi nomor unik.

### 4.5 Import Data
- Import CSV/Excel.
- Mapping kolom.
- Validasi dan laporan error per baris.
- Simpan log import.

### 4.6 Foto & Media
- Upload foto satuan/massal.
- Crop/resize ke ukuran standar cetak.
- Validasi dimensi dan mime type.

### 4.7 Template Kartu
- Kelola template front/back.
- Elemen dinamis berbasis konfigurasi JSON.
- Komponen branding: logo, stempel, tanda tangan, nama jabatan pimpinan.

### 4.8 Generate & Export
- Generate kartu per individu/batch.
- Output PDF/PNG.
- Export massal ke ZIP.
- Standar Output Cetak: Grid 2x5 (10 kartu per A4) dengan ukuran standar ID-1 (85.6mm x 54mm).
- Riwayat generate.

### 4.9 Audit & Logging
- Catat aktivitas penting: login, import, update template, generate kartu.
- Catat status job queue.

---

## 5. Data Model (Tingkat Tinggi)

## 5.1 Tabel Inti
- `users`
- `institutions`
- `classes`
- `students`
- `card_types`
- `card_templates`
- `template_elements` (opsional jika tidak full JSON)
- `media_assets`
- `imports`
- `generate_batches`
- `generated_cards`
- `activity_logs`

### 5.2 Entitas Utama

#### users
- `id`
- `name`
- `email`
- `password`
- `role` (`admin|guru`)
- `institution_id`
- `is_active`
- `deleted_at` (SoftDeletes)
- timestamps

#### institutions
- `id`
- `name`
- `address`
- `phone`
- `email`
- `leader_name`
- `leader_title`
- `logo_media_id` (FK media_assets)
- `stamp_media_id` (FK media_assets)
- `leader_signature_media_id` (FK media_assets)
- `deleted_at` (SoftDeletes)
- timestamps

#### students
- `id`
- `institution_id`
- `class_id`
- `student_code` (unik)
- `name`
- `exam_number` (nullable)
- `school_name`
- `gender`
- `address`
- `phone`
- `social_instagram` (nullable)
- `social_facebook` (nullable)
- `social_tiktok` (nullable)
- `status`
- `deleted_at` (SoftDeletes)
- timestamps

#### media_assets
- `id`
- `owner_type` (institution/student/template/generated_card)
- `owner_id`
- `category`
- `disk` (default `s3`)
- `bucket`
- `object_key`
- `original_name`
- `mime_type`
- `size_bytes`
- `checksum` (MD5/SHA1 untuk de-duplikasi)
- `width` (nullable)
- `height` (nullable)
- `uploaded_by` (FK users)
- timestamps

#### card_templates
- `id`
- `institution_id` (nullable jika global)
- `card_type_id`
- `name`
- `width_mm`
- `height_mm`
- `background_front_media_id` (nullable)
- `background_back_media_id` (nullable)
- `config_json`
- `is_active`
- timestamps

#### generate_batches
- `id`
- `template_id`
- `requested_by`
- `status` (`pending|processing|done|failed`)
- `total_cards`
- `success_count`
- `failed_count`
- `started_at`
- `finished_at`
- timestamps

#### generated_cards
- `id`
- `batch_id`
- `student_id`
- `template_id`
- `front_media_id` (hasil render)
- `back_media_id` (nullable)
- `pdf_media_id` (nullable)
- `asset_snapshot_json`
- `status`
- `error_message` (nullable)
- timestamps

---

## 6. Desain Object Storage MinIO

Gunakan bucket tunggal: `card-app` (private).

Prefix object:
- `institutions/{institution_id}/branding/logo/{file}`
- `institutions/{institution_id}/branding/stamp/{file}`
- `institutions/{institution_id}/branding/signature/{file}`
- `students/{student_id}/photos/original/{file}`
- `students/{student_id}/photos/processed/{file}`
- `templates/{template_id}/background/front/{file}`
- `templates/{template_id}/background/back/{file}`
- `generated/{batch_id}/{student_id}/front/{file}`
- `generated/{batch_id}/{student_id}/back/{file}`
- `generated/{batch_id}/{student_id}/pdf/{file}`
- `exports/{user_id}/{export_id}/{file}`

Konvensi nama file:
- `YYYYMMDD_uuid.ext`
- Hindari nama file berbasis nama peserta.

---

## 7. Konfigurasi Laravel untuk MinIO

Contoh variabel `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=card-app
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=http://minio:9000
AWS_URL=http://localhost:9000/card-app
```

Catatan:
- Bucket tetap private.
- Akses unduh gunakan presigned URL (TTL pendek, misal 10 menit) atau proxy backend.

---

## 8. Desain Template Kartu

`config_json` menyimpan posisi elemen dinamis.

Contoh:

```json
{
  "canvas": { "width_mm": 86, "height_mm": 54 },
  "elements": [
    { "type": "photo", "key": "student_photo", "x": 6, "y": 10, "w": 20, "h": 26, "z": 10 },
    { "type": "text", "key": "name", "x": 30, "y": 14, "font_size": 10, "font_weight": "700", "z": 20 },
    { "type": "text", "key": "student_code", "x": 30, "y": 20, "font_size": 8, "z": 20 },
    { "type": "image", "key": "institution_logo", "x": 6, "y": 4, "w": 10, "h": 10, "z": 30 },
    { "type": "image", "key": "institution_stamp", "x": 58, "y": 26, "w": 20, "h": 20, "opacity": 0.55, "z": 40 },
    { "type": "image", "key": "leader_signature", "x": 58, "y": 40, "w": 20, "h": 8, "z": 50 },
    { "type": "text", "key": "leader_name", "x": 58, "y": 49, "font_size": 6, "z": 60 },
    { "type": "text", "key": "leader_title", "x": 58, "y": 52, "font_size": 5, "z": 60 }
  ]
}
```

Aturan fallback:
- Jika template tidak override branding, pakai branding default dari `institutions`.
- Saat generate, simpan `asset_snapshot_json` agar hasil historis tidak berubah.

---

## 9. Alur Utama Sistem

### 9.1 Alur Setup Awal (Admin)
1. Admin login.
2. Isi profil instansi.
3. Upload logo, stempel, tanda tangan pimpinan.
4. Buat user guru.
5. Buat template kartu (dilengkapi Live Preview sederhana).
6. Setup branding instansi (logo, stempel, tanda tangan).

### 9.2 Alur Operasional (Guru)
1. Import data peserta atau input manual.
2. Upload/import foto (Mendukung Auto-mapping berdasarkan student_code).
3. Pilih template.
4. Pilih data peserta (single/batch).
5. Generate kartu (dengan progress bar real-time).
6. Download PDF/ZIP.

### 9.3 Alur Queue
1. Request generate masuk queue.
2. Worker mengambil job.
3. Render kartu per peserta.
4. Simpan hasil ke MinIO.
5. Update status batch (`done`/`failed`).

---

## 10. Standar Validasi & Keamanan

### 10.1 Validasi Data
- `student_code` wajib unik per instansi.
- Data wajib minimal: nama, kode, kelas (sesuai kebijakan).
- File hanya mime type yang diizinkan.

### 10.2 Validasi File
- Foto: `jpg/jpeg/png/webp`.
- Stempel/ttd: disarankan `png` transparan.
- Maksimum ukuran file ditentukan (misal 2MB-5MB).
- Minimal resolusi untuk cetak (misal sisi terpanjang >= 1000 px).

### 10.3 Security
- Bucket MinIO private.
- Presigned URL TTL singkat.
- Cegah IDOR: policy harus validasi kepemilikan scope user.
- Audit log untuk aktivitas sensitif.

---

## 11. Non-Fungsional

- Kinerja:
- Import/generate massal harus asynchronous (queue).
- Pagination semua listing besar.

- Reliabilitas:
- Retry job gagal.
- Simpan error detail per record.

- Backup:
- Backup PostgreSQL terjadwal.
- Backup object MinIO terjadwal.

- Observability:
- Monitor queue depth, failed jobs, durasi generate per batch.

---

## 12. Roadmap Implementasi

### Fase 1 (MVP)
- Auth + role admin/guru.
- CRUD peserta.
- Upload foto.
- CRUD template dasar.
- Branding instansi (logo, stempel, tanda tangan).
- Generate single + batch sederhana.
- Export PDF.

### Fase 2
- Import CSV/Excel dengan mapping kolom.
- Import foto massal.
- ZIP export.
- Audit log detail.
- Monitoring queue.

### Fase 3
- Template designer drag-and-drop.
- QR/Barcode verifikasi.
- Multi-instansi/cabang penuh.
- Integrasi API eksternal (SIAKAD/ERP).

---

## 13. API/Endpoint Awal (Contoh)

- `POST /auth/login`
- `POST /auth/logout`
- `GET /users`
- `POST /users`
- `GET /students`
- `POST /students`
- `POST /students/import`
- `POST /media/upload`
- `POST /templates`
- `POST /generate/batches`
- `GET /generate/batches/{id}`
- `GET /exports/{id}/download`

---

## 14. Keputusan Desain Utama

- File media tidak disimpan di database sebagai blob.
- Semua file disimpan di MinIO, database menyimpan metadata.
- Semua proses berat dijalankan di queue.
- Hasil generate menyimpan snapshot aset branding untuk konsistensi historis.

---

## 15. Deliverable Teknis Berikutnya

Setelah blueprint ini disetujui, tahap berikut:
1. Finalisasi ERD.
2. Daftar migration Laravel.
3. Kontrak endpoint (request/response).
4. Docker Compose baseline (`app`, `worker`, `postgres`, `redis`, `minio`).
5. Skeleton project Laravel + Inertia + Vue.

