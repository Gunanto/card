<?php

namespace App\Jobs;

use App\Models\GenerateBatch;
use App\Models\GeneratedCard;
use App\Models\MediaAsset;
use App\Services\MediaAssetService;
use App\Support\SimplePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class RenderGeneratedCardJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $generatedCardId)
    {
    }

    public function handle(MediaAssetService $mediaAssetService): void
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

            $snapshot = [
                'institution' => [
                    'logo' => $this->mediaSnapshot($institution->logoMedia),
                    'stamp' => $this->mediaSnapshot($institution->stampMedia),
                    'signature' => $this->mediaSnapshot($institution->leaderSignatureMedia),
                ],
                'student' => [
                    'photo' => $this->mediaSnapshot($studentPhoto),
                ],
                'template' => [
                    'background_front' => $this->mediaSnapshot($template->backgroundFrontMedia),
                    'background_back' => $this->mediaSnapshot($template->backgroundBackMedia),
                ],
            ];

            $basePath = sprintf('generated/%d/%d', $batch->id, $student->id);
            $frontAsset = $mediaAssetService->storeContent(
                $this->frontSvg($generatedCard, $snapshot),
                'generated_front',
                $generatedCard,
                $batch->requestedBy,
                $basePath.'/front',
                'svg',
                'image/svg+xml',
            );
            $backAsset = $mediaAssetService->storeContent(
                $this->backSvg($generatedCard),
                'generated_back',
                $generatedCard,
                $batch->requestedBy,
                $basePath.'/back',
                'svg',
                'image/svg+xml',
            );
            $pdfAsset = $mediaAssetService->storeContent(
                $this->pdfDocument($generatedCard),
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

    protected function frontSvg(GeneratedCard $generatedCard, array $snapshot): string
    {
        $student = $generatedCard->student;
        $institution = $generatedCard->batch->institution;
        $classroom = $student->classroom?->name ?? '-';
        $photoStatus = $snapshot['student']['photo']['object_key'] ?? 'missing';
        $logoStatus = $snapshot['institution']['logo']['object_key'] ?? 'missing';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="856" height="540" viewBox="0 0 856 540">
  <rect width="856" height="540" rx="28" fill="#f8fafc" />
  <rect x="24" y="24" width="808" height="492" rx="24" fill="#e2e8f0" />
  <rect x="54" y="94" width="190" height="246" rx="18" fill="#cbd5e1" stroke="#475569" stroke-width="4" />
  <text x="149" y="225" text-anchor="middle" font-size="28" fill="#334155">PHOTO</text>
  <text x="149" y="265" text-anchor="middle" font-size="16" fill="#475569">{$this->svgEscape($photoStatus)}</text>
  <rect x="676" y="46" width="110" height="110" rx="18" fill="#bfdbfe" stroke="#1d4ed8" stroke-width="4" />
  <text x="731" y="105" text-anchor="middle" font-size="24" fill="#1e3a8a">LOGO</text>
  <text x="731" y="132" text-anchor="middle" font-size="12" fill="#1e3a8a">{$this->svgEscape($logoStatus)}</text>
  <text x="280" y="120" font-size="38" font-weight="700" fill="#0f172a">{$this->svgEscape($institution->name)}</text>
  <text x="280" y="172" font-size="26" fill="#334155">{$this->svgEscape($generatedCard->template->name)}</text>
  <text x="280" y="252" font-size="42" font-weight="700" fill="#111827">{$this->svgEscape($student->name)}</text>
  <text x="280" y="302" font-size="26" fill="#374151">Kode: {$this->svgEscape($student->student_code)}</text>
  <text x="280" y="340" font-size="26" fill="#374151">Ujian: {$this->svgEscape($student->exam_number ?? '-')}</text>
  <text x="280" y="378" font-size="26" fill="#374151">Kelas: {$this->svgEscape($classroom)}</text>
  <text x="280" y="416" font-size="24" fill="#475569">Pimpinan: {$this->svgEscape($institution->leader_name ?? '-')}</text>
  <text x="280" y="454" font-size="24" fill="#475569">Jabatan: {$this->svgEscape($institution->leader_title ?? '-')}</text>
  <rect x="604" y="312" width="148" height="148" rx="24" fill="#fecaca" fill-opacity="0.65" stroke="#b91c1c" stroke-width="4" />
  <text x="678" y="392" text-anchor="middle" font-size="26" fill="#991b1b">STAMP</text>
  <line x1="584" y1="462" x2="786" y2="462" stroke="#111827" stroke-width="4" />
  <text x="684" y="490" text-anchor="middle" font-size="18" fill="#111827">{$this->svgEscape($institution->leader_name ?? '-')}</text>
</svg>
SVG;
    }

    protected function backSvg(GeneratedCard $generatedCard): string
    {
        $institution = $generatedCard->batch->institution;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="856" height="540" viewBox="0 0 856 540">
  <rect width="856" height="540" rx="28" fill="#082f49" />
  <rect x="32" y="32" width="792" height="476" rx="24" fill="#0f172a" stroke="#38bdf8" stroke-width="4" />
  <text x="428" y="130" text-anchor="middle" font-size="42" font-weight="700" fill="#e0f2fe">{$this->svgEscape($institution->name)}</text>
  <text x="428" y="190" text-anchor="middle" font-size="28" fill="#bae6fd">Kartu sisi belakang</text>
  <text x="428" y="286" text-anchor="middle" font-size="24" fill="#e2e8f0">Alamat: {$this->svgEscape($institution->address ?? '-')}</text>
  <text x="428" y="326" text-anchor="middle" font-size="24" fill="#e2e8f0">Telepon: {$this->svgEscape($institution->phone ?? '-')}</text>
  <text x="428" y="366" text-anchor="middle" font-size="24" fill="#e2e8f0">Email: {$this->svgEscape($institution->email ?? '-')}</text>
  <text x="428" y="450" text-anchor="middle" font-size="18" fill="#7dd3fc">Generated by batch #{$generatedCard->batch_id}</text>
</svg>
SVG;
    }

    protected function pdfDocument(GeneratedCard $generatedCard): string
    {
        $student = $generatedCard->student;
        $institution = $generatedCard->batch->institution;

        return SimplePdf::fromLines([
            'Card App MVP Export',
            'Batch ID: '.$generatedCard->batch_id,
            'Generated Card ID: '.$generatedCard->id,
            'Institution: '.$institution->name,
            'Template: '.$generatedCard->template->name,
            'Student: '.$student->name,
            'Student Code: '.$student->student_code,
            'Exam Number: '.($student->exam_number ?? '-'),
            'Generated At: '.now()->toDateTimeString(),
        ]);
    }

    protected function mediaSnapshot(?MediaAsset $mediaAsset): ?array
    {
        if (! $mediaAsset) {
            return null;
        }

        return [
            'id' => $mediaAsset->id,
            'category' => $mediaAsset->category,
            'object_key' => $mediaAsset->object_key,
            'checksum' => $mediaAsset->checksum,
        ];
    }

    protected function svgEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
