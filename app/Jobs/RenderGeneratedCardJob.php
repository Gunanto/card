<?php

namespace App\Jobs;

use App\Models\GenerateBatch;
use App\Models\GeneratedCard;
use App\Models\MediaAsset;
use App\Services\MediaAssetService;
use App\Services\TemplateDataResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RenderGeneratedCardJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $generatedCardId)
    {
    }

    public function handle(MediaAssetService $mediaAssetService, TemplateDataResolver $templateDataResolver): void
    {
        $generatedCard = GeneratedCard::query()
            ->with([
                'batch.requestedBy',
                'batch.institution.logoMedia',
                'batch.institution.stampMedia',
                'batch.institution.leaderSignatureMedia',
                'student.classroom',
                'template',
            ])
            ->find($this->generatedCardId);

        if (! $generatedCard) {
            return;
        }

        $generatedCard->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $student = $generatedCard->student;
            $batch = $generatedCard->batch;
            $institution = $batch->institution;
            $template = $generatedCard->template;
            $studentPhoto = $student->mediaAssets()->where('category', 'student_photo')->latest('id')->first();
            $assetMap = [
                'student_photo' => $studentPhoto,
                'institution_logo' => $institution->logoMedia,
                'institution_stamp' => $institution->stampMedia,
                'leader_signature' => $institution->leaderSignatureMedia,
                'template_background_front' => $template->backgroundFrontMedia,
                'template_background_back' => $template->backgroundBackMedia,
            ];

            $snapshot = [
                'institution' => [
                    'logo' => $this->mediaSnapshot($assetMap['institution_logo']),
                    'stamp' => $this->mediaSnapshot($assetMap['institution_stamp']),
                    'signature' => $this->mediaSnapshot($assetMap['leader_signature']),
                ],
                'student' => [
                    'photo' => $this->mediaSnapshot($assetMap['student_photo']),
                ],
                'template' => [
                    'background_front' => $this->mediaSnapshot($assetMap['template_background_front']),
                    'background_back' => $this->mediaSnapshot($assetMap['template_background_back']),
                ],
            ];

            $basePath = sprintf('generated/%d/%d', $batch->id, $student->id);
            $frontSvg = $this->frontSvg($generatedCard, $assetMap, $templateDataResolver);
            $backSvg = $this->backSvg($generatedCard, $assetMap);

            $frontAsset = $mediaAssetService->storeContent(
                $frontSvg,
                'generated_front',
                $generatedCard,
                $batch->requestedBy,
                $basePath.'/front',
                'svg',
                'image/svg+xml',
            );
            $backAsset = $mediaAssetService->storeContent(
                $backSvg,
                'generated_back',
                $generatedCard,
                $batch->requestedBy,
                $basePath.'/back',
                'svg',
                'image/svg+xml',
            );
            $pdfAsset = $mediaAssetService->storeContent(
                $this->pdfDocument($generatedCard, $frontSvg, $backSvg),
                'generated_pdf',
                $generatedCard,
                $batch->requestedBy,
                $basePath.'/pdf',
                'pdf',
                'application/pdf',
            );

            $generatedCard->update([
                'front_media_id' => $frontAsset->id,
                'back_media_id' => $backAsset->id,
                'pdf_media_id' => $pdfAsset->id,
                'asset_snapshot_json' => $snapshot,
                'status' => 'done',
                'error_message' => null,
            ]);
        } catch (Throwable $throwable) {
            $generatedCard->update([
                'status' => 'failed',
                'error_message' => Str::limit($throwable->getMessage(), 65535),
            ]);
        }

        $this->refreshBatch($generatedCard->batch()->firstOrFail());
    }

    protected function refreshBatch(GenerateBatch $batch): void
    {
        $counts = GeneratedCard::query()
            ->where('batch_id', $batch->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($counts['pending'] ?? 0);
        $processing = (int) ($counts['processing'] ?? 0);
        $success = (int) ($counts['done'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $isFinished = ($pending + $processing) === 0;

        $batch->update([
            'status' => $isFinished ? ($failed > 0 ? 'failed' : 'done') : 'processing',
            'success_count' => $success,
            'failed_count' => $failed,
            'finished_at' => $isFinished ? now() : null,
        ]);
    }

    protected function frontSvg(GeneratedCard $generatedCard, array $assetMap, TemplateDataResolver $templateDataResolver): string
    {
        $template = $generatedCard->template;
        $config = is_array($template->config_json) ? $template->config_json : [];
        $canvasWidthMm = $this->number($config['canvas']['width_mm'] ?? null, (float) $template->width_mm);
        $canvasHeightMm = $this->number($config['canvas']['height_mm'] ?? null, (float) $template->height_mm);
        $pxPerMm = 10.0;
        $widthPx = (int) max(1, round($canvasWidthMm * $pxPerMm));
        $heightPx = (int) max(1, round($canvasHeightMm * $pxPerMm));
        $elements = is_array($config['elements'] ?? null)
            ? array_values(array_filter($config['elements'], 'is_array'))
            : [];
        usort($elements, fn (array $a, array $b): int => $this->zIndex($a) <=> $this->zIndex($b));

        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
            $widthPx,
            $heightPx,
            $widthPx,
            $heightPx,
        );
        $svg[] = sprintf('<rect width="%d" height="%d" fill="#f8fafc" />', $widthPx, $heightPx);

        $backgroundUri = $this->mediaDataUri($assetMap['template_background_front'] ?? null);
        if ($backgroundUri !== null) {
            $svg[] = sprintf(
                '<image x="0" y="0" width="%d" height="%d" href="%s" preserveAspectRatio="xMidYMid slice" />',
                $widthPx,
                $heightPx,
                $this->svgEscape($backgroundUri),
            );
        }

        $nonTextElements = array_values(array_filter(
            $elements,
            fn (array $element): bool => (string) ($element['type'] ?? '') !== 'text',
        ));
        $textElements = array_values(array_filter(
            $elements,
            fn (array $element): bool => (string) ($element['type'] ?? '') === 'text',
        ));

        // Render text last to keep dynamic values readable above background/overlay images.
        foreach (array_merge($nonTextElements, $textElements) as $element) {
            $type = (string) ($element['type'] ?? '');
            $xPx = $this->mmToPx($this->axisValue($element, 'x'), $pxPerMm);
            $yPx = $this->mmToPx($this->axisValue($element, 'y'), $pxPerMm);
            $wPx = $this->mmToPx($this->number($element['w'] ?? $element['w_mm'] ?? null, 0), $pxPerMm);
            $hPx = $this->mmToPx($this->number($element['h'] ?? $element['h_mm'] ?? null, 0), $pxPerMm);
            $opacity = $this->number($element['opacity'] ?? null, 1);

            if (in_array($type, ['photo', 'image'], true)) {
                $imageAsset = $templateDataResolver->resolveImageAsset($element, $assetMap);
                $imageUri = $this->mediaDataUri($imageAsset);
                if ($imageUri === null || $wPx <= 0 || $hPx <= 0) {
                    continue;
                }

                $svg[] = sprintf(
                    '<image x="%s" y="%s" width="%s" height="%s" href="%s" opacity="%s" preserveAspectRatio="xMidYMid slice" />',
                    $this->fmt($xPx),
                    $this->fmt($yPx),
                    $this->fmt($wPx),
                    $this->fmt($hPx),
                    $this->svgEscape($imageUri),
                    $this->fmt($opacity),
                );
                continue;
            }

            if ($type === 'text') {
                $value = $templateDataResolver->resolveTextValue($element, $generatedCard);
                if ($value === '') {
                    continue;
                }

                $fontSizeMm = $this->number($element['font_size'] ?? $element['font_size_mm'] ?? null, 2.8);
                $fontSizePx = $this->mmToPx($fontSizeMm, $pxPerMm);
                $fontWeight = (string) ($element['font_weight'] ?? '400');
                $fill = (string) ($element['color'] ?? '#111827');
                $anchor = (string) ($element['text_anchor'] ?? 'start');
                // Many PDF/SVG renderers treat y as baseline and may ignore dominant-baseline.
                // Shift y to baseline so coordinates behave closer to top-left positioning in editor.
                $textBaselineYPx = $yPx + ($fontSizePx * 0.84);
                $svg[] = sprintf(
                    '<text x="%s" y="%s" font-size="%s" font-family="%s" font-weight="%s" fill="%s" opacity="%s" text-anchor="%s">%s</text>',
                    $this->fmt($xPx),
                    $this->fmt($textBaselineYPx),
                    $this->fmt($fontSizePx),
                    $this->svgEscape('DejaVu Sans, Arial, sans-serif'),
                    $this->svgEscape($fontWeight),
                    $this->svgEscape($fill),
                    $this->fmt($opacity),
                    $this->svgEscape($anchor),
                    $this->svgEscape($value),
                );
            }
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    protected function backSvg(GeneratedCard $generatedCard, array $assetMap): string
    {
        $institution = $generatedCard->batch->institution;
        $template = $generatedCard->template;
        $canvasWidthMm = (float) $template->width_mm;
        $canvasHeightMm = (float) $template->height_mm;
        $pxPerMm = 10.0;
        $widthPx = (int) max(1, round($canvasWidthMm * $pxPerMm));
        $heightPx = (int) max(1, round($canvasHeightMm * $pxPerMm));
        $backgroundUri = $this->mediaDataUri($assetMap['template_background_back'] ?? null);

        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
            $widthPx,
            $heightPx,
            $widthPx,
            $heightPx,
        );
        $svg[] = sprintf('<rect width="%d" height="%d" fill="#0f172a" />', $widthPx, $heightPx);

        if ($backgroundUri !== null) {
            $svg[] = sprintf(
                '<image x="0" y="0" width="%d" height="%d" href="%s" preserveAspectRatio="xMidYMid slice" />',
                $widthPx,
                $heightPx,
                $this->svgEscape($backgroundUri),
            );
        }

        $svg[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-size="%d" font-weight="700" fill="#e5e7eb">%s</text>',
            (int) round($widthPx / 2),
            (int) round($heightPx * 0.30),
            22,
            $this->svgEscape($institution->name),
        );
        $svg[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-size="%d" fill="#d1d5db">%s</text>',
            (int) round($widthPx / 2),
            (int) round($heightPx * 0.45),
            14,
            $this->svgEscape('Alamat: '.($institution->address ?? '-')),
        );
        $svg[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-size="%d" fill="#d1d5db">%s</text>',
            (int) round($widthPx / 2),
            (int) round($heightPx * 0.53),
            14,
            $this->svgEscape('Telepon: '.($institution->phone ?? '-')),
        );
        $svg[] = sprintf(
            '<text x="%d" y="%d" text-anchor="middle" font-size="%d" fill="#d1d5db">%s</text>',
            (int) round($widthPx / 2),
            (int) round($heightPx * 0.61),
            14,
            $this->svgEscape('Email: '.($institution->email ?? '-')),
        );
        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    protected function pdfDocument(GeneratedCard $generatedCard, string $frontSvg, string $backSvg): string
    {
        $template = $generatedCard->template;
        $student = $generatedCard->student;
        $cardWidthMm = max(20.0, (float) $template->width_mm);
        $cardHeightMm = max(20.0, (float) $template->height_mm);
        $marginMm = 8.0;
        $gapMm = 6.0;
        $paperWidthPt = $this->mmToPt($cardWidthMm + ($marginMm * 2));
        $paperHeightPt = $this->mmToPt(($cardHeightMm * 2) + ($marginMm * 2) + $gapMm);

        $studentLabel = $this->htmlEscape($student->name.' / '.$student->student_code);
        $frontUri = $this->svgDataUri($frontSvg);
        $backUri = $this->svgDataUri($backSvg);
        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            padding: {$marginMm}mm;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
        }
        .card {
            width: {$cardWidthMm}mm;
            height: {$cardHeightMm}mm;
            border: 0.3mm solid #d1d5db;
            overflow: hidden;
        }
        .card + .card {
            margin-top: {$gapMm}mm;
        }
        .card img {
            width: 100%;
            height: 100%;
            object-fit: fill;
            display: block;
        }
        .meta {
            margin-top: 2mm;
            font-size: 9px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="card"><img src="{$frontUri}" alt="Front card" /></div>
    <div class="card"><img src="{$backUri}" alt="Back card" /></div>
    <div class="meta">{$studentLabel}</div>
</body>
</html>
HTML;

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, $paperWidthPt, $paperHeightPt], 'portrait')
            ->output();
    }

    protected function svgDataUri(string $svg): string
    {
        return sprintf('data:image/svg+xml;base64,%s', base64_encode($svg));
    }

    protected function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    protected function htmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
    }

    protected function mediaSnapshot(?MediaAsset $mediaAsset): ?array
    {
        if (! $mediaAsset) {
            return null;
        }

        return [
            'id' => $mediaAsset->id,
            'category' => $mediaAsset->category,
            'disk' => $mediaAsset->disk,
            'mime_type' => $mediaAsset->mime_type,
            'object_key' => $mediaAsset->object_key,
            'checksum' => $mediaAsset->checksum,
        ];
    }

    protected function mediaDataUri(?MediaAsset $mediaAsset): ?string
    {
        if (! $mediaAsset) {
            return null;
        }

        try {
            $content = Storage::disk($mediaAsset->disk)->get($mediaAsset->object_key);
        } catch (Throwable) {
            return null;
        }

        $mimeType = $mediaAsset->mime_type ?: 'application/octet-stream';

        // DomPDF often fails to render WEBP inside nested SVG <image> tags.
        // Convert WEBP bytes to PNG so template backgrounds stay visible in generated PDFs.
        if ($mimeType === 'image/webp') {
            $converted = $this->convertImageToPng($content);
            if ($converted !== null) {
                $content = $converted;
                $mimeType = 'image/png';
            }
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($content));
    }

    protected function convertImageToPng(string $content): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return null;
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! $ok || ! is_string($png) || $png === '') {
            return null;
        }

        return $png;
    }

    protected function zIndex(array $element): int
    {
        return (int) ($element['z'] ?? $element['z_index'] ?? 10);
    }

    protected function axisValue(array $element, string $axis): float
    {
        return $this->number($element[$axis] ?? $element[$axis.'_mm'] ?? null, 0);
    }

    protected function number(mixed $value, float $default = 0): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    protected function mmToPx(float $mm, float $pxPerMm): float
    {
        return $mm * $pxPerMm;
    }

    protected function fmt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    protected function svgEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
