<?php

namespace Database\Seeders;

use App\Models\CardTemplate;
use App\Models\CardType;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::updateOrCreate(
            ['name' => 'SMK Contoh Nusantara'],
            [
                'npsn' => '20123456',
                'address' => 'Jl. Pendidikan No. 10, Jakarta',
                'village' => 'Cempaka Putih Timur',
                'district' => 'Cempaka Putih',
                'regency' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'postal_code' => '10510',
                'phone' => '0215550101',
                'email' => 'info@smkcontoh.test',
                'website' => 'https://smkcontoh.test',
                'leader_name' => 'Dewi Lestari',
                'leader_title' => 'Kepala Sekolah',
            ],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@card.test'],
            [
                'institution_id' => null,
                'name' => 'Admin Card',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $guru = User::updateOrCreate(
            ['email' => 'guru@card.test'],
            [
                'institution_id' => $institution->id,
                'name' => 'Guru Demo',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'guru',
                'is_active' => true,
            ],
        );

        $classroomA = Classroom::updateOrCreate(
            ['institution_id' => $institution->id, 'code' => 'X-TKJ-1'],
            [
                'name' => 'X TKJ 1',
                'level' => '10',
                'major' => 'Teknik Komputer dan Jaringan',
                'homeroom_teacher_user_id' => $guru->id,
            ],
        );

        $classroomB = Classroom::updateOrCreate(
            ['institution_id' => $institution->id, 'code' => 'X-TKJ-2'],
            [
                'name' => 'X TKJ 2',
                'level' => '10',
                'major' => 'Teknik Komputer dan Jaringan',
                'homeroom_teacher_user_id' => $guru->id,
            ],
        );

        Student::updateOrCreate(
            ['institution_id' => $institution->id, 'student_code' => 'SIS-0001'],
            [
                'class_id' => $classroomA->id,
                'nis' => '1001001',
                'nisn' => '0099988877',
                'nik' => '3171010101010001',
                'npwp' => '09.999.888.7-001.000',
                'exam_number' => 'UJ-2026-0001',
                'name' => 'Alya Putri',
                'school_name' => $institution->name,
                'gender' => 'female',
                'religion' => 'Islam',
                'address' => 'Jl. Melati No. 1',
                'village' => 'Cempaka Putih Timur',
                'district' => 'Cempaka Putih',
                'regency' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'phone' => '081234567890',
                'mobile_phone' => '081234567890',
                'motto' => 'Belajar hari ini, memimpin esok.',
                'social_instagram' => '@alyaputri',
                'status' => 'active',
            ],
        );

        Student::updateOrCreate(
            ['institution_id' => $institution->id, 'student_code' => 'SIS-0002'],
            [
                'class_id' => $classroomB->id,
                'nis' => '1001002',
                'nisn' => '0099988878',
                'nik' => '3171010101010002',
                'npwp' => '09.999.888.7-002.000',
                'exam_number' => 'UJ-2026-0002',
                'name' => 'Bima Prakoso',
                'school_name' => $institution->name,
                'gender' => 'male',
                'religion' => 'Islam',
                'address' => 'Jl. Kenanga No. 2',
                'village' => 'Cempaka Putih Timur',
                'district' => 'Cempaka Putih',
                'regency' => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'phone' => '081234567891',
                'mobile_phone' => '081234567891',
                'motto' => 'Disiplin adalah kunci prestasi.',
                'social_tiktok' => '@bimaprakoso',
                'status' => 'active',
            ],
        );

        $cardType = CardType::query()->where('code', 'student')->firstOrFail();

        CardTemplate::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Template Default Kartu Pelajar',
            ],
            [
                'card_type_id' => $cardType->id,
                'width_mm' => 85.60,
                'height_mm' => 54.00,
                'config_json' => [
                    [
                        'type' => 'image',
                        'key' => 'student_photo',
                        'label' => 'Foto Siswa',
                        'x_mm' => 5,
                        'y_mm' => 8,
                        'w_mm' => 18,
                        'h_mm' => 24,
                    ],
                    [
                        'type' => 'image',
                        'key' => 'institution_logo',
                        'label' => 'Logo Instansi',
                        'x_mm' => 68,
                        'y_mm' => 5,
                        'w_mm' => 12,
                        'h_mm' => 12,
                    ],
                    [
                        'type' => 'text',
                        'key' => 'student_name',
                        'label' => 'Nama Siswa',
                        'x_mm' => 26,
                        'y_mm' => 14,
                        'w_mm' => 52,
                        'h_mm' => 6,
                    ],
                    [
                        'type' => 'text',
                        'key' => 'student_code',
                        'label' => 'NIS',
                        'x_mm' => 26,
                        'y_mm' => 22,
                        'w_mm' => 45,
                        'h_mm' => 5,
                    ],
                    [
                        'type' => 'image',
                        'key' => 'institution_stamp',
                        'label' => 'Stempel Instansi',
                        'x_mm' => 55,
                        'y_mm' => 33,
                        'w_mm' => 14,
                        'h_mm' => 14,
                    ],
                    [
                        'type' => 'image',
                        'key' => 'leader_signature',
                        'label' => 'Tanda Tangan Pimpinan',
                        'x_mm' => 61,
                        'y_mm' => 36,
                        'w_mm' => 18,
                        'h_mm' => 8,
                    ],
                    [
                        'type' => 'text',
                        'key' => 'leader_name',
                        'label' => 'Nama Pimpinan',
                        'x_mm' => 50,
                        'y_mm' => 45,
                        'w_mm' => 30,
                        'h_mm' => 4,
                    ],
                    [
                        'type' => 'text',
                        'key' => 'leader_title',
                        'label' => 'Jabatan Pimpinan',
                        'x_mm' => 50,
                        'y_mm' => 49,
                        'w_mm' => 30,
                        'h_mm' => 4,
                    ],
                ],
                'print_layout_json' => [
                    'page_size' => 'A4',
                    'orientation' => 'portrait',
                    'grid' => ['columns' => 2, 'rows' => 5],
                    'card_size_mm' => ['width' => 85.6, 'height' => 54],
                    'gap_mm' => ['x' => 5, 'y' => 5],
                    'padding_mm' => ['top' => 9.5, 'right' => 16.9, 'bottom' => 9.5, 'left' => 16.9],
                    'print_margin_mode' => 'none',
                ],
                'is_active' => true,
            ],
        );
    }
}
