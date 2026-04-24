<?php

namespace App\Services;

use App\Models\Import;
use App\Models\Student;
use App\Models\User;
use RuntimeException;
use ZipArchive;

class StudentPhotoImportService
{
    protected const MAX_PHOTO_BYTES = 512000; // 500 KB

    protected const MIN_WIDTH = 300;

    protected const MIN_HEIGHT = 300;

    public function __construct(protected MediaAssetService $mediaAssetService)
    {
    }

    public function process(Import $import, string $absoluteZipPath, ?User $actor = null, ?callable $onProgress = null): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum tersedia di environment.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($absoluteZipPath);

        if ($opened !== true) {
            throw new RuntimeException('File ZIP tidak dapat dibuka.');
        }

        try {
            $entries = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = (string) $zip->getNameIndex($index);

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $entries[] = [
                    'index' => $index,
                    'entry_name' => $entryName,
                    'extension' => $extension,
                ];
            }

            $errors = [];
            $success = 0;
            $failed = 0;
            $total = count($entries);

            if ($total === 0) {
                throw new RuntimeException('ZIP tidak berisi file foto valid (jpg/jpeg/png/webp).');
            }

            $onProgress?($success, $failed, $total);

            foreach ($entries as $position => $entry) {
                try {
                    $entryName = $entry['entry_name'];
                    $extension = $entry['extension'];
                    $stat = $zip->statIndex($entry['index']);
                    $studentCode = trim((string) pathinfo($entryName, PATHINFO_FILENAME));

                    if ($studentCode === '') {
                        throw new RuntimeException('Nama file tidak valid untuk pemetaan student_code.');
                    }

                    $sizeBytes = (int) ($stat['size'] ?? 0);
                    if ($sizeBytes <= 0) {
                        throw new RuntimeException('File foto kosong.');
                    }
                    if ($sizeBytes > self::MAX_PHOTO_BYTES) {
                        throw new RuntimeException(sprintf(
                            'Ukuran file %s melebihi batas 500KB.',
                            $this->formatBytes($sizeBytes),
                        ));
                    }

                    $student = Student::query()
                        ->where('institution_id', $import->institution_id)
                        ->whereRaw('LOWER(student_code) = ?', [strtolower($studentCode)])
                        ->first();

                    if (! $student) {
                        throw new RuntimeException("Siswa dengan student_code '{$studentCode}' tidak ditemukan.");
                    }

                    $content = $zip->getFromIndex($entry['index']);

                    if (! is_string($content) || $content === '') {
                        throw new RuntimeException('File foto kosong atau gagal dibaca.');
                    }

                    [$width, $height] = $this->imageDimensions($content);
                    if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
                        throw new RuntimeException(sprintf(
                            'Dimensi foto terlalu kecil (%dx%d). Minimal %dx%d.',
                            $width,
                            $height,
                            self::MIN_WIDTH,
                            self::MIN_HEIGHT,
                        ));
                    }

                    $mimeType = $this->mimeTypeForExtension($extension);

                    $this->mediaAssetService->storeContent(
                        content: $content,
                        category: 'student_photo',
                        owner: $student,
                        uploadedBy: $actor,
                        pathPrefix: sprintf('students/%d/photos/original', $student->id),
                        extension: $extension,
                        mimeType: $mimeType,
                    );

                    $success++;
                } catch (\Throwable $throwable) {
                    $failed++;
                    $errors[] = [
                        'row' => $position + 1,
                        'file' => $entry['entry_name'],
                        'message' => $throwable->getMessage(),
                    ];
                }

                $onProgress?($success, $failed, $total);
            }
        } finally {
            $zip->close();
        }

        return [
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'error_summary_json' => array_slice($errors, 0, 100),
        ];
    }

    protected function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    protected function imageDimensions(string $binary): array
    {
        $size = @getimagesizefromstring($binary);

        if (! is_array($size)) {
            throw new RuntimeException('File bukan gambar valid atau format gambar rusak.');
        }

        return [
            (int) ($size[0] ?? 0),
            (int) ($size[1] ?? 0),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
    }
}
