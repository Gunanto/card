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

## 6) Rencana Implementasi: Template Background + Binding Data DB + Drag & Drop

Target utama:
- Background kartu disiapkan sebagai media template.
- Data identitas sekolah/siswa diambil dinamis dari database.
- Posisi elemen bisa diatur via drag-and-drop dan tersimpan di `config_json`.
- Hasil preview editor konsisten dengan hasil generate kartu dan PDF.

### Fase 1 - Kontrak Konfigurasi Elemen (config_json v2)
- Definisikan schema elemen yang eksplisit (tanpa memutus kompatibilitas v1):
  - `type`: `text | image | photo`
  - `mode`: `dynamic | static` (khusus `text`)
  - `source`: path data terstruktur (mis. `student.name`, `student.exam_number`, `institution.name`, `institution.leader_name`)
  - `text`: nilai literal untuk label statis (mis. `NO URUT`, `Kepala Sekolah`)
  - `x`, `y`, `w`, `h`, `z`, `opacity`, `font_size`, `font_weight`, `color`, `text_anchor`
- Buat resolver backward compatibility:
  - key lama (`name`, `student_code`, dst) tetap didukung.
  - saat save template baru, serialisasi ke format v2.
- Tambahkan validasi schema minimum di request template:
  - elemen wajib punya field posisi valid.
  - elemen `text` wajib salah satu: `mode=static+text` atau `mode=dynamic+source`.
  - elemen `image/photo` wajib `source` valid.

### Fase 2 - Data Binding Layer untuk Renderer
- Implement `TemplateDataResolver` (service terpisah) yang menyiapkan payload render:
  - `student.*` (name, student_code, exam_number, class_name, school_name, dll).
  - `institution.*` (name, address, phone, email, leader_name, leader_title, dll).
  - Media source (`student.photo`, `institution.logo`, `institution.stamp`, `institution.signature`, `template.background_front`, `template.background_back`).
- Tambahkan strategy fallback:
  - jika field kosong, gunakan string aman (`-`) sesuai konteks.
  - jika media tidak ada, skip render elemen image/photo tanpa fail batch.

### Fase 3 - Upgrade Visual Editor
- Panel properti elemen di UI template:
  - dropdown `Type`.
  - untuk `text`: switch `Static/Dynamic`.
  - jika `dynamic`: dropdown `Data Source` (daftar field DB terstandar).
  - jika `static`: input `Text`.
  - kontrol posisi/ukuran/typography tetap ada.
- Tambahkan preset cepat:
  - `+ Nama`, `+ Nomor Ujian`, `+ Kelas`, `+ Logo`, `+ Foto`, `+ TTD`, `+ Stempel`, `+ Label Statis`.
- Pastikan `Reload Editor dari JSON` mendukung format v2 dan tidak menghapus properti baru.

### Fase 4 - Konsistensi Output (Editor = Generate)
- Refactor `RenderGeneratedCardJob` agar seluruh elemen dibaca dari resolver + schema v2.
- Hentikan pembuatan PDF per-siswa placeholder berbasis `SimplePdf::fromLines`.
- Ganti PDF per-siswa menjadi render visual kartu final (front/back) yang sama dengan editor.
- Pertahankan snapshot asset (`asset_snapshot_json`) untuk audit reproduksibilitas.

### Fase 5 - Layout Cetak A4 Dinamis
- Implement parser `print_layout_json` ke engine A4:
  - `page_size`, `orientation`, `rows`, `cols`, `gap_x_mm`, `gap_y_mm`,
  - `page_margin_mm`, `card_size_mm`, `show_cut_guides`.
- Ubah view PDF agar tidak hardcoded 2x5; gunakan parameter template aktif.
- Tambahkan preset bawaan:
  - A4 2x5 (ID card standar 85.6x54 mm),
  - opsi orientasi portrait/landscape.

### Fase 6 - Data Model Tambahan yang Dibutuhkan
- Tambahkan field institusi yang umum di kartu ujian jika dibutuhkan desain:
  - `leader_nip` (opsional).
- (Opsional) Tambah field siswa:
  - `exam_seat_number` / `exam_room` jika kebutuhan kartu ujian menuntut.
- Sinkronkan form UI + import mapping untuk field tambahan ini.

### Fase 7 - Keamanan Akses & Operasional
- Perbaiki ownership media `generated_batch_pdf` agar scope institusi, bukan hanya uploader.
- Verifikasi policy akses media untuk guru lintas user dalam institusi yang sama.
- Pastikan runtime Docker production mendukung import ZIP/XLSX (aktifkan ekstensi `zip`).

### Fase 8 - QA, UAT, dan Kriteria Terima
- Tambah test feature minimum:
  - generate batch dengan elemen static+dynamic.
  - template global vs institusi.
  - akses download batch PDF antar guru dalam institusi sama.
- Buat test dataset UAT kartu ujian:
  - data lengkap sekolah, pimpinan, siswa, foto.
- Checklist acceptance:
  - Background tampil tepat.
  - Drag-and-drop tersimpan akurat.
  - Field DB tampil sesuai source.
  - Hasil PDF A4 konsisten dengan preview editor.

## 7) Rencana Implementasi Dark/Light Mode (Bertahap & Minim Risiko)

Tujuan:
- Menyediakan mode `light` dan `dark` di seluruh aplikasi tanpa refactor besar sekaligus.
- Menjaga stabilitas UI operasional (dashboard, form CRUD, import, generate, monitoring).

Prinsip implementasi:
- Terapkan bertahap per-layout lalu per-halaman, bukan big-bang.
- Hindari hardcoded warna baru; pakai token warna terpusat.
- Rilis dengan feature flag agar mudah rollback.

### Fase 1 - Fondasi Theme (Token + Strategy)
- Tetapkan strategi dark mode berbasis class (`html.dark`) agar kontrol eksplisit.
- Definisikan token semantik minimum:
  - `--bg`, `--surface`, `--surface-2`, `--text`, `--text-muted`, `--border`, `--primary`.
- Mapping token ke utility class Tailwind/CSS agar komponen lama bisa migrasi bertahap.
- Tambahkan fallback aman untuk browser lama (default light).

### Fase 2 - Theme State & Persistensi
- Buat store/composable theme (mis. `useTheme`) untuk:
  - `light | dark | system`.
  - simpan preferensi ke `localStorage`.
  - sinkronisasi ke class root document.
- Tambahkan toggle di `AuthenticatedLayout` dan `GuestLayout`.
- Tambahkan inisialisasi awal sebelum app mount untuk menghindari flash mode.

### Fase 3 - Migrasi Komponen Shared (Prioritas Tinggi)
- Migrasikan komponen reusable dulu:
  - button, input, select, textarea, table wrapper, modal, badge, card, dropdown.
- Ganti penggunaan warna hardcoded ke token semantik.
- Tambahkan state aksesibilitas:
  - hover/focus/disabled/error harus tetap terbaca di dark dan light.

### Fase 4 - Migrasi Halaman Operasional
- Urutan migrasi halaman:
  1. `Dashboard`
  2. `Students`, `Classrooms`, `Institutions`
  3. `Imports`, `GenerateBatches`, `MediaAssets`
  4. `CardTemplates`, `Users`, `Profile`
- Setiap halaman wajib lolos visual check desktop + mobile sebelum lanjut ke halaman berikutnya.

### Fase 5 - Halaman Publik & Landing
- Sinkronkan `Welcome.vue` dengan token theme, termasuk section hero, card fitur, roadmap, footer.
- Pastikan kontras teks merah brand (`#FF2D20`) tetap memenuhi keterbacaan di background dark.

### Fase 6 - Integrasi Pihak Ketiga & Aset
- Audit komponen pihak ketiga (datepicker, chart, preview library jika ada).
- Siapkan varian aset untuk dark bila perlu (logo/ikon yang hilang kontras).
- Validasi screenshot untuk komponen yang tidak otomatis mengikuti class dark.

### Fase 7 - QA, A11y, dan Gate Rilis
- Tambah checklist QA tema:
  - kontras teks minimum WCAG AA untuk teks normal.
  - tidak ada teks/informasi yang hilang di dark mode.
  - focus ring tetap terlihat.
- Tambah smoke test visual untuk rute utama pada 2 mode.
- Rilis bertahap:
  - fase 1: internal/staging.
  - fase 2: enable ke sebagian user (opsional).
  - fase 3: default aktif setelah stabil.

### Risiko & Mitigasi
- Risiko: inkonsistensi warna lintas halaman.
  - Mitigasi: token semantik + migrasi komponen shared terlebih dahulu.
- Risiko: komponen lama hardcoded sulit terbaca di dark.
  - Mitigasi: lint rule/review checklist untuk larangan hardcoded warna baru.
- Risiko: regresi visual setelah merge besar.
  - Mitigasi: batching kecil per modul + screenshot regression check.

### Definisi Selesai Dark/Light Mode
- Toggle mode tersedia di guest dan authenticated layout.
- Semua halaman utama terbaca baik pada light/dark (tanpa teks hilang).
- Komponen inti (form, table, modal, nav) konsisten di kedua mode.
- Tidak ada blocker aksesibilitas kritis (kontras/focus) pada flow utama.

### Breakdown Eksekusi Mingguan (Siap Operasional)

#### Week 1 - Fondasi & Shared Components
- [ ] Setup token warna semantik global (`--bg`, `--surface`, `--text`, `--border`, dst).
- [ ] Aktifkan strategy `html.dark` + mode `light/dark/system`.
- [ ] Implement `useTheme` + persistensi `localStorage`.
- [ ] Tambahkan toggle mode di `AuthenticatedLayout` + `GuestLayout`.
- [ ] Migrasi komponen shared prioritas:
  - [ ] button
  - [ ] input/select/textarea
  - [ ] modal
  - [ ] dropdown
  - [ ] badge/card/table wrapper
- [ ] QA cepat: cek visual 2 mode pada halaman login + dashboard.

#### Week 2 - Migrasi Halaman Operasional
- [ ] Migrasi `Dashboard` ke token theme.
- [ ] Migrasi `Students`, `Classrooms`, `Institutions`.
- [ ] Migrasi `Imports`, `GenerateBatches`, `MediaAssets`.
- [ ] Migrasi `CardTemplates`, `Users`, `Profile`.
- [ ] Rapikan hardcoded warna yang tersisa pada komponen halaman.
- [ ] QA per-modul desktop/mobile (light/dark) sebelum lanjut modul berikutnya.

#### Week 3 - Halaman Publik, Third-Party, dan Hardening
- [ ] Sinkronkan `Welcome.vue` full token dark/light.
- [ ] Validasi warna brand merah tetap readable pada dark background.
- [ ] Audit komponen/asset third-party (chart/datepicker/preview).
- [ ] Tambah smoke test visual untuk rute utama (2 mode).
- [ ] Tambah checklist aksesibilitas (kontras, focus ring, disabled/error state).
- [ ] Uji staging + bugfix final.

#### Week 4 - Rollout Bertahap & Stabilization
- [ ] Aktifkan via feature flag untuk internal.
- [ ] Monitoring error visual/regresi dari feedback user internal.
- [ ] Perbaikan cepat untuk edge-case lintas browser/device.
- [ ] Aktifkan umum setelah sign-off QA + UAT.

### PIC & Output per Minggu (Template)
- PIC Frontend:
  - Output: PR komponen/theme + screenshot before/after.
- PIC QA:
  - Output: checklist validasi 2 mode per halaman + issue list.
- PIC Reviewer:
  - Output: approval aksesibilitas minimum + keputusan go/no-go release.

### Estimasi Effort (S/M/L + Jam)

Catatan skala:
- `S` = kecil (1-3 jam)
- `M` = menengah (4-8 jam)
- `L` = besar (9-16 jam)

#### Week 1 - Fondasi & Shared Components
- Setup token warna semantik global: `M (4-6 jam)`
- Strategy `html.dark` + mode `light/dark/system`: `S (2-3 jam)`
- `useTheme` + persistensi `localStorage`: `S (2-3 jam)`
- Toggle mode di 2 layout utama: `S (1-2 jam)`
- Migrasi komponen shared prioritas: `L (10-14 jam)`
- QA cepat login + dashboard: `S (2-3 jam)`
- Total indikatif Week 1: `21-31 jam`

#### Week 2 - Migrasi Halaman Operasional
- Dashboard: `S (2-3 jam)`
- Students/Classrooms/Institutions: `L (9-14 jam)`
- Imports/GenerateBatches/MediaAssets: `L (9-14 jam)`
- CardTemplates/Users/Profile: `L (9-14 jam)`
- Rapikan hardcoded warna tersisa: `M (4-6 jam)`
- QA per-modul desktop/mobile: `M (5-8 jam)`
- Total indikatif Week 2: `38-59 jam`

#### Week 3 - Publik, Third-Party, Hardening
- Sinkronisasi `Welcome.vue` full theme: `S (2-4 jam)`
- Validasi kontras warna brand: `S (1-2 jam)`
- Audit komponen/asset third-party: `M (4-8 jam)`
- Smoke test visual rute utama (2 mode): `M (4-6 jam)`
- Checklist aksesibilitas + perbaikan: `M (5-8 jam)`
- Uji staging + bugfix final: `M (4-8 jam)`
- Total indikatif Week 3: `20-36 jam`

#### Week 4 - Rollout & Stabilization
- Aktivasi feature flag internal: `S (1-2 jam)`
- Monitoring feedback + triase issue: `M (4-8 jam)`
- Fix edge-case browser/device: `M (4-8 jam)`
- Aktivasi umum + sign-off: `S (2-3 jam)`
- Total indikatif Week 4: `11-21 jam`

#### Total Program (indikatif)
- Rentang total: `90-147 jam`.
- Dengan 1 frontend + 1 QA paruh waktu: estimasi `3-4 minggu`.
