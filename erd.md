# ERD Aplikasi Kartu

Dokumen ini menurunkan [blueprint.md](/home/pgun/dev/card/blueprint.md) ke model relasional yang siap diimplementasikan di Laravel + PostgreSQL, dengan pertimbangan layout cetak A4 dari [a4_layout.html](/home/pgun/dev/card/a4_layout.html).

## Prinsip Desain
- Multi-instansi dengan user `admin` dan `guru`.
- Semua file media (foto, logo, stempel, tanda tangan, output generate) disimpan di MinIO dan direferensikan lewat `media_assets`.
- Template menyimpan konfigurasi elemen kartu dan konfigurasi cetak A4 2x5.
- Hasil generate menyimpan snapshot aset untuk menjaga histori tetap konsisten.

## ERD (Mermaid)
```mermaid
erDiagram
  institutions ||--o{ users : has
  institutions ||--o{ classes : has
  institutions ||--o{ students : has
  institutions ||--o{ card_templates : owns

  classes ||--o{ students : groups
  card_types ||--o{ card_templates : categorizes

  users ||--o{ media_assets : uploads
  users ||--o{ imports : runs
  users ||--o{ generate_batches : requests
  users ||--o{ activity_logs : acts

  card_templates ||--o{ generate_batches : used_by
  generate_batches ||--o{ generated_cards : contains
  students ||--o{ generated_cards : printed_for
  card_templates ||--o{ generated_cards : rendered_with

  users {
    bigint id PK
    bigint institution_id FK
    string name
    string email UK
    string role
    boolean is_active
    timestamp deleted_at
  }

  institutions {
    bigint id PK
    string name
    text address
    string phone
    string email
    string leader_name
    string leader_title
    bigint logo_media_id FK
    bigint stamp_media_id FK
    bigint leader_signature_media_id FK
    timestamp deleted_at
  }

  classes {
    bigint id PK
    bigint institution_id FK
    string code
    string name
    string level
    string major
    bigint homeroom_teacher_user_id FK
    timestamp deleted_at
  }

  students {
    bigint id PK
    bigint institution_id FK
    bigint class_id FK
    string student_code
    string exam_number
    string name
    string school_name
    string gender
    text address
    string phone
    string social_instagram
    string social_facebook
    string social_tiktok
    string status
    timestamp deleted_at
  }

  card_types {
    bigint id PK
    string code UK
    string name
    text description
  }

  media_assets {
    bigint id PK
    string owner_type
    bigint owner_id
    string category
    string disk
    string bucket
    string object_key
    string original_name
    string mime_type
    bigint size_bytes
    string checksum
    int width
    int height
    bigint uploaded_by FK
  }

  card_templates {
    bigint id PK
    bigint institution_id FK
    bigint card_type_id FK
    string name
    decimal width_mm
    decimal height_mm
    bigint background_front_media_id FK
    bigint background_back_media_id FK
    json config_json
    json print_layout_json
    boolean is_active
  }

  imports {
    bigint id PK
    bigint institution_id FK
    bigint imported_by FK
    string type
    string source_filename
    json mapping_json
    int total_rows
    int success_rows
    int failed_rows
    string status
    json error_summary_json
  }

  generate_batches {
    bigint id PK
    bigint template_id FK
    bigint requested_by FK
    bigint institution_id FK
    string status
    int total_cards
    int success_count
    int failed_count
    timestamp started_at
    timestamp finished_at
    json options_json
  }

  generated_cards {
    bigint id PK
    bigint batch_id FK
    bigint student_id FK
    bigint template_id FK
    bigint front_media_id FK
    bigint back_media_id FK
    bigint pdf_media_id FK
    json asset_snapshot_json
    string status
    text error_message
  }

  activity_logs {
    bigint id PK
    bigint user_id FK
    bigint institution_id FK
    string action
    string subject_type
    bigint subject_id
    inet ip_address
    string user_agent
    json metadata_json
  }
```

## Catatan Relasi Penting
- `students.student_code` harus unik per `institution_id`.
- `card_templates.institution_id` nullable untuk template global.
- `media_assets` menggunakan pasangan `owner_type + owner_id` untuk polymorphic ownership.
- `institutions.logo_media_id`, `stamp_media_id`, `leader_signature_media_id` menunjuk ke `media_assets.id`.
- `generated_cards.asset_snapshot_json` menyimpan object key aset branding saat render untuk histori immutable.

## Standar Layout Cetak A4 (Turunan dari a4_layout.html)
- Ukuran kartu ID-1: `85.6mm x 54mm`.
- Kertas: `A4 210mm x 297mm`.
- Grid default: `2 kolom x 5 baris` (10 kartu per halaman).
- Gap default: `5mm x 5mm`.
- Padding default halaman: `top 9.5mm`, `right 16.9mm`, `bottom 9.5mm`, `left 16.9mm`.

Nilai di atas disimpan dalam `card_templates.print_layout_json` agar bisa di-override per template tanpa ubah kode.

Contoh `print_layout_json`:
```json
{
  "page_size": "A4",
  "orientation": "portrait",
  "grid": { "columns": 2, "rows": 5 },
  "card_size_mm": { "width": 85.6, "height": 54 },
  "gap_mm": { "x": 5, "y": 5 },
  "padding_mm": { "top": 9.5, "right": 16.9, "bottom": 9.5, "left": 16.9 },
  "print_margin_mode": "none"
}
```

