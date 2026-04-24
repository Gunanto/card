# To_Do - Hasil Audit & Lanjutan

Dokumen ini adalah hasil audit aplikasi terhadap [blueprint.md](/home/pgun/dev/card/blueprint.md) dan [erd.md](/home/pgun/dev/card/erd.md), lalu dijadikan rencana kerja lanjutan yang sinkron dengan kondisi kode saat ini.

Tanggal audit: **Jumat, 24 April 2026 (malam)**  
Scope audit: migration, model, request validation, controller, service, job, route, dan UI utama.

## 1) Ringkasan Status

### DONE
- Auth + role (`admin`, `guru`) + middleware user aktif.
- CRUD dasar: `institutions`, `classrooms`, `students`, `card_templates`, `users`.
- Import siswa CSV/Excel async + mapping + error summary + UI monitoring.
- Import foto siswa massal via ZIP (berdasarkan `student_code`) async + error summary.
- Progress bar import determinate di UI admin/guru (0-100% berbasis progres job).
- Upload media ke MinIO + metadata `media_assets` + presigned URL download/preview.
- Generate batch async per-student + simpan `generated_cards` + `asset_snapshot_json`.
- Download PDF batch A4 2x5 via `dompdf`.
- Seeder dasar tersedia: `card_types`, admin, guru, institution demo, classroom demo, student demo, template demo.
- Struktur tabel inti sesuai ERD sudah ada (termasuk `imports`, `activity_logs`, `template_elements`).

### PARTIAL
- Renderer kartu sudah membaca `config_json` untuk elemen dasar (text/photo/image) pada sisi front, dan visual editor MVP (drag/resize/properti + snap grid + undo/redo dasar) sudah tersedia; namun elemen lanjutan dan editor production-grade masih belum lengkap.
- PDF A4 2x5 sudah jadi, tapi masih mengandalkan output kartu placeholder (SVG), bukan render desain template final.
- Audit log backend sudah terpasang di flow utama (auth, CRUD inti, import, generate, download), namun UI monitoring log belum ada.
- QA bisnis end-to-end belum terdokumentasi/terotomasi (baru validasi basic build/migrate/syntax).

### TODO
- Monitoring queue di dashboard (depth, failed jobs, durasi).
- Export massal ZIP.
- Verifikasi akurasi ukuran cetak fisik (mm) terhadap hasil PDF.
- Parser/renderer template final dari `config_json`.
- Kontrak endpoint formal (request/response) per modul.

## 2) Temuan Audit Penting

1. `generated_batch_pdf` disimpan tanpa owner (`owner_type`/`owner_id` null), sehingga akses download bisa terlalu sempit antar-guru satu institusi.  
   Dampak: batch yang sama bisa gagal diunduh oleh guru lain dalam institusi yang sama jika bukan uploader.
2. Tabel `activity_logs` sudah ada, tapi belum dipakai oleh flow aplikasi.  
   Dampak: requirement audit trail dari blueprint belum terpenuhi.
3. `template_elements` sudah ada di DB/model, tetapi belum dipakai saat render kartu.  
   Dampak: fleksibilitas template belum terealisasi pada output final.

## 3) Prioritas Lanjutan (Urutan Eksekusi)

1. Perbaiki ownership aset `generated_batch_pdf` agar mengikuti scope institusi (bukan hanya uploader).
2. Tambah halaman monitoring `activity_logs` (filter action, user, instansi, rentang waktu).
3. Implement renderer template berbasis `config_json` (front/back) dan gunakan saat generate.
4. Sinkronkan PDF A4 agar membaca `print_layout_json` (grid, gap, padding, orientation).
5. Tambah export ZIP massal.
6. Tambah dashboard monitoring queue + failed jobs.
7. Lengkapi test skenario bisnis minimum (import, generate, authorization lintas instansi).

## 4) Checklist Teknis (Update)

### A. Environment & Infra
- [x] `docker-compose` untuk `postgres`, `redis`, `minio`.
- [x] Bucket MinIO `card-app` private.
- [x] `.env` Laravel untuk PostgreSQL, Redis, MinIO.

### B. Database & Seeder
- [x] Migration inti ERD terpasang.
- [x] Constraint kunci (`students` unique per institusi, FK branding, unique generated card per batch+student).
- [x] Seeder `card_types` + akun demo + data demo.

### C. Feature Utama
- [x] Auth + role guard.
- [x] CRUD users (admin only).
- [x] CRUD institutions/classrooms/students.
- [x] Upload media + metadata + presigned URL.
- [x] CRUD templates + default `print_layout_json`.
- [x] Generate batch + queue + status tracking.
- [x] Import CSV/Excel async + mapping + error summary.
- [x] Import foto siswa ZIP async (mapping nama file -> `student_code`).
- [x] Progress bar import determinate pada modul import.
- [x] Download PDF A4 2x5 per batch.

### D. Gap Fungsional
- [ ] Renderer kartu final berbasis `config_json`.
- [ ] A4 layout dinamis dari `print_layout_json`.
- [ ] Export ZIP.
- [~] Audit log terintegrasi (backend done, UI monitoring log belum).
- [ ] Queue observability di dashboard.
- [ ] Verifikasi print fisik (mm).

## 5) Definisi Selesai MVP Internal (Revisi)

MVP internal dianggap **selesai penuh** jika:
- Admin bisa setup branding instansi dan manajemen user guru.
- Guru bisa input/import siswa + upload foto.
- Generate kartu massal memakai renderer template real (bukan placeholder).
- Hasil unduh PDF A4 2x5 konsisten ukuran cetak fisik.
- Akses file private MinIO konsisten berdasarkan scope instansi.
- Aktivitas sensitif tercatat di `activity_logs` dan bisa dimonitor dari UI admin.
