<?php

namespace App\Services;

use App\Models\GenerateBatch;
use App\Models\GeneratedCard;
use App\Models\MediaAsset;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class BatchPdfService
{
    public function generateAndStore(GenerateBatch $batch, User $user): MediaAsset
    {
        $batch->loadMissing([
            'institution',
            'template',
            'generatedCards.student',
            'generatedCards.frontMedia',
        ]);

        $cards = $batch->generatedCards
            ->filter(fn (GeneratedCard $card): bool => $card->status === 'done' && $card->frontMedia !== null)
            ->map(function (GeneratedCard $card): array {
                return [
                    'student_name' => $card->student?->name ?? '-',
                    'student_code' => $card->student?->student_code ?? '-',
                    'exam_number' => $card->student?->exam_number ?? '-',
                    'front_image_data_uri' => $this->frontImageDataUri($card),
                ];
            })
            ->values()
            ->all();

        if ($cards === []) {
            throw new \RuntimeException('Batch belum memiliki kartu siap cetak.');
        }

        $html = view('pdf.batch-a4-2x5', [
            'batch' => $batch,
            'cards' => $cards,
        ])->render();

        $pdfBinary = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->output();

        $pathPrefix = sprintf('exports/batches/%d/a4_2x5', $batch->id);

        return app(MediaAssetService::class)->storeContent(
            $pdfBinary,
            'generated_batch_pdf',
            $batch->institution,
            $user,
            $pathPrefix,
            'pdf',
            'application/pdf',
        );
    }

    protected function frontImageDataUri(GeneratedCard $card): string
    {
        $mediaAsset = $card->frontMedia;
        $content = Storage::disk($mediaAsset->disk)->get($mediaAsset->object_key);
        $mime = $mediaAsset->mime_type ?: 'image/svg+xml';

        return sprintf('data:%s;base64,%s', $mime, base64_encode($content));
    }
}
