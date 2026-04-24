# Draft Migration Notes

Folder ini berisi draft migration Laravel turunan dari [blueprint.md](/home/pgun/dev/card/blueprint.md) dan [erd.md](/home/pgun/dev/card/erd.md).

## Urutan dan Dependensi
1. `create_institutions_table`
2. `create_users_table`
3. `create_classes_table`
4. `create_students_table`
5. `create_card_types_table`
6. `create_media_assets_table`
7. `add_branding_media_fk_to_institutions_table`
8. `create_card_templates_table`
9. `create_imports_table`
10. `create_generate_batches_table`
11. `create_generated_cards_table`
12. `create_activity_logs_table`
13. `create_template_elements_table` (opsional)

## Keputusan Desain
- `media_assets` memakai pola polymorphic sederhana (`owner_type`, `owner_id`).
- Branding instansi (`logo/stamp/signature`) pakai FK ke `media_assets`, ditambahkan setelah tabel `media_assets` dibuat.
- `card_templates.print_layout_json` memuat preset cetak A4 2x5 (10 kartu per halaman) agar fleksibel per template.
- `template_elements` optional karena elemen utama bisa disimpan penuh di `config_json`.

## Catatan Implementasi di Project Laravel
- Saat integrasi ke project Laravel nyata, pindahkan file migration ke folder `database/migrations/`.
- Sesuaikan timestamp migration jika ingin memakai `php artisan make:migration`.
- Tambahkan seed awal untuk `card_types` (`exam`, `student`, `library`, dll).

