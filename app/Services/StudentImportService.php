<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Import;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class StudentImportService
{
    public function process(Import $import, string $absoluteFilePath, ?callable $onProgress = null): array
    {
        $rows = $this->readRows($absoluteFilePath);
        $mapping = is_array($import->mapping_json) ? $import->mapping_json : [];
        $errors = [];
        $success = 0;
        $failed = 0;
        $total = count($rows);
        $onProgress?($success, $failed, $total);

        foreach ($rows as $index => $row) {
            try {
                $payload = $this->mapStudentPayload($row, $mapping);
                $studentCode = trim((string) ($payload['student_code'] ?? ''));
                $name = trim((string) ($payload['name'] ?? ''));

                if ($studentCode === '' || $name === '') {
                    throw new RuntimeException('Kolom wajib `student_code` dan `name` harus terisi.');
                }

                $classId = $this->resolveClassroomId($import->institution_id, $payload);

                Student::query()->updateOrCreate(
                    [
                        'institution_id' => $import->institution_id,
                        'student_code' => $studentCode,
                    ],
                    [
                        'class_id' => $classId,
                        'nis' => $payload['nis'] ?? null,
                        'nisn' => $payload['nisn'] ?? null,
                        'nik' => $payload['nik'] ?? null,
                        'npwp' => $payload['npwp'] ?? null,
                        'exam_number' => $payload['exam_number'] ?? null,
                        'name' => $name,
                        'school_name' => $payload['school_name'] ?? null,
                        'gender' => $payload['gender'] ?? null,
                        'religion' => $payload['religion'] ?? null,
                        'address' => $payload['address'] ?? null,
                        'village' => $payload['village'] ?? null,
                        'district' => $payload['district'] ?? null,
                        'regency' => $payload['regency'] ?? null,
                        'province' => $payload['province'] ?? null,
                        'phone' => $payload['phone'] ?? null,
                        'mobile_phone' => $payload['mobile_phone'] ?? null,
                        'motto' => $payload['motto'] ?? null,
                        'social_instagram' => $payload['social_instagram'] ?? null,
                        'social_facebook' => $payload['social_facebook'] ?? null,
                        'social_tiktok' => $payload['social_tiktok'] ?? null,
                        'status' => $payload['status'] ?? 'active',
                    ],
                );

                $success++;
            } catch (\Throwable $throwable) {
                $failed++;
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $throwable->getMessage(),
                    'raw' => Arr::only($row, ['student_code', 'name', 'nis', 'nisn', 'class_code', 'class_name']),
                ];
            }

            $onProgress?($success, $failed, $total);
        }

        return [
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'error_summary_json' => array_slice($errors, 0, 100),
        ];
    }

    protected function readRows(string $absoluteFilePath): array
    {
        $spreadsheet = IOFactory::load($absoluteFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = (int) $sheet->getHighestRow();
        $headers = [];
        $rows = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $raw = (string) $sheet->getCell([$column, 1])->getFormattedValue();
            $headers[] = $this->normalizeHeader($raw ?: 'column_'.$column);
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $assoc = [];
            $nonEmpty = false;

            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = (string) $sheet->getCell([$column, $row])->getFormattedValue();
                $value = trim($value);
                $assoc[$headers[$column - 1]] = $value === '' ? null : $value;
                $nonEmpty = $nonEmpty || $value !== '';
            }

            if ($nonEmpty) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    protected function mapStudentPayload(array $row, array $mapping): array
    {
        $fields = [
            'student_code',
            'nis',
            'nisn',
            'nik',
            'npwp',
            'exam_number',
            'name',
            'school_name',
            'gender',
            'religion',
            'address',
            'village',
            'district',
            'regency',
            'province',
            'phone',
            'mobile_phone',
            'motto',
            'social_instagram',
            'social_facebook',
            'social_tiktok',
            'status',
            'class_code',
            'class_name',
        ];

        $payload = [];

        foreach ($fields as $field) {
            $source = $mapping[$field] ?? $field;
            $value = $row[$this->normalizeHeader($source)] ?? null;
            $payload[$field] = is_string($value) ? trim($value) : $value;
        }

        if ($payload['gender'] !== null) {
            $normalizedGender = strtolower((string) $payload['gender']);
            $payload['gender'] = match ($normalizedGender) {
                'male', 'm' => 'male',
                'female', 'f' => 'female',
                default => null,
            };
        }

        if ($payload['status'] !== null) {
            $normalizedStatus = strtolower((string) $payload['status']);
            $payload['status'] = in_array($normalizedStatus, ['active', 'inactive', 'graduated'], true)
                ? $normalizedStatus
                : 'active';
        }

        return $payload;
    }

    protected function resolveClassroomId(int $institutionId, array $payload): ?int
    {
        $classCode = trim((string) ($payload['class_code'] ?? ''));
        $className = trim((string) ($payload['class_name'] ?? ''));

        if ($classCode !== '') {
            $classroom = Classroom::query()->firstOrCreate(
                [
                    'institution_id' => $institutionId,
                    'code' => $classCode,
                ],
                [
                    'name' => $className !== '' ? $className : $classCode,
                ],
            );

            return $classroom->id;
        }

        if ($className !== '') {
            $classroom = Classroom::query()->where('institution_id', $institutionId)->where('name', $className)->first();

            return $classroom?->id;
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
