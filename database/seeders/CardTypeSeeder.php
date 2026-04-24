<?php

namespace Database\Seeders;

use App\Models\CardType;
use Illuminate\Database\Seeder;

class CardTypeSeeder extends Seeder
{
    public function run(): void
    {
        CardType::upsert([
            [
                'code' => 'exam',
                'name' => 'Kartu Ujian',
                'description' => 'Kartu peserta ujian.',
            ],
            [
                'code' => 'student',
                'name' => 'Kartu Pelajar',
                'description' => 'Kartu identitas siswa.',
            ],
            [
                'code' => 'library',
                'name' => 'Kartu Perpustakaan',
                'description' => 'Kartu anggota perpustakaan.',
            ],
            [
                'code' => 'event',
                'name' => 'Kartu Kegiatan',
                'description' => 'Kartu untuk event atau ekstrakurikuler.',
            ],
            [
                'code' => 'custom',
                'name' => 'Kartu Custom',
                'description' => 'Template kartu fleksibel untuk kebutuhan khusus.',
            ],
        ], ['code'], ['name', 'description']);
    }
}
