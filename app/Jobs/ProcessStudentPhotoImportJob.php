<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\User;
use App\Services\StudentPhotoImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessStudentPhotoImportJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(
        public int $importId,
        public string $storagePath,
        public string $disk = 'local',
    ) {
    }

    public function handle(StudentPhotoImportService $studentPhotoImportService): void
    {
        $import = Import::query()->find($this->importId);

        if (! $import) {
            return;
        }

        $import->update([
            'status' => 'processing',
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'error_summary_json' => null,
        ]);

        try {
            $content = Storage::disk($this->disk)->get($this->storagePath);
            $tmpPath = storage_path('app/private/tmp/import_photo_'.Str::uuid().'.'.pathinfo($this->storagePath, PATHINFO_EXTENSION));
            @mkdir(dirname($tmpPath), 0775, true);
            file_put_contents($tmpPath, $content);

            $actor = User::query()->find($import->imported_by);
            $result = $studentPhotoImportService->process(
                $import,
                $tmpPath,
                $actor,
                function (int $success, int $failed, int $total) use ($import): void {
                    $import->update([
                        'total_rows' => $total,
                        'success_rows' => $success,
                        'failed_rows' => $failed,
                    ]);
                },
            );

            @unlink($tmpPath);

            $import->update([
                ...$result,
                'status' => ($result['failed_rows'] > 0 && $result['success_rows'] === 0) ? 'failed' : 'done',
            ]);
        } catch (\Throwable $throwable) {
            $import->update([
                'status' => 'failed',
                'error_summary_json' => [
                    [
                        'message' => $throwable->getMessage(),
                    ],
                ],
            ]);
        }
    }
}
