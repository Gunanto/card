<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerateBatchRequest;
use App\Jobs\RenderGeneratedCardJob;
use App\Models\CardTemplate;
use App\Models\GenerateBatch;
use App\Models\GeneratedCard;
use App\Models\MediaAsset;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\BatchPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GenerateBatchController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $templates = $this->visibleTemplateQuery($user)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $students = $this->scopeByInstitution(
            Student::query()->with(['classroom', 'institution'])->orderBy('name'),
            $user,
        )->get();
        $batches = $this->scopeByInstitution(
            GenerateBatch::query()->with([
                'template',
                'institution',
                'requestedBy',
                'generatedCards.student',
                'generatedCards.pdfMedia',
            ]),
            $user,
        )->latest()->limit(20)->get();

        return Inertia::render('GenerateBatches/Index', [
            'templates' => $templates->map(fn (CardTemplate $template): array => [
                'id' => $template->id,
                'institution_id' => $template->institution_id,
                'name' => $template->name,
            ])->values(),
            'students' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'institution_id' => $student->institution_id,
                'name' => $student->name,
                'student_code' => $student->student_code,
                'classroom_name' => $student->classroom?->name,
                'institution_name' => $student->institution?->name,
            ])->values(),
            'batches' => $batches->map(fn (GenerateBatch $batch): array => [
                'id' => $batch->id,
                'institution_name' => $batch->institution?->name,
                'template_name' => $batch->template?->name,
                'requested_by_name' => $batch->requestedBy?->name,
                'status' => $batch->status,
                'total_cards' => $batch->total_cards,
                'success_count' => $batch->success_count,
                'failed_count' => $batch->failed_count,
                'created_at' => $batch->created_at?->toDateTimeString(),
                'finished_at' => $batch->finished_at?->toDateTimeString(),
                'a4_pdf_download_url' => $this->a4PdfDownloadUrl($batch),
                'a4_pdf_resolve_url' => $this->a4PdfResolveUrl($batch),
                'generated_cards' => $batch->generatedCards->map(fn (GeneratedCard $generatedCard): array => [
                    'id' => $generatedCard->id,
                    'student_name' => $generatedCard->student?->name,
                    'status' => $generatedCard->status,
                    'error_message' => $generatedCard->error_message,
                    'pdf_download_url' => $generatedCard->pdfMedia
                        ? route('media-assets.download', $generatedCard->pdfMedia)
                        : null,
                    'pdf_stream_download_url' => $generatedCard->pdfMedia
                        ? route('media-assets.stream', $generatedCard->pdfMedia)
                        : null,
                ])->values(),
            ])->values(),
            'defaultOptionsJsonText' => "{}",
        ]);
    }

    public function store(StoreGenerateBatchRequest $request): RedirectResponse
    {
        $user = $request->user();
        $template = $this->visibleTemplateQuery($user)->findOrFail($request->integer('template_id'));
        $students = $this->scopeByInstitution(
            Student::query()->whereIn('id', $request->input('student_ids', [])),
            $user,
        )->get();

        abort_unless($students->count() === count($request->input('student_ids', [])), 422);

        $institutionIds = $students->pluck('institution_id')->unique()->values();
        abort_unless($institutionIds->count() === 1, 422);

        $institutionId = (int) $institutionIds->first();

        if ($template->institution_id !== null) {
            abort_unless($template->institution_id === $institutionId, 422);
        }

        $options = $request->optionsPayload();

        $batch = DB::transaction(function () use ($students, $template, $user, $institutionId, $options): GenerateBatch {
            $batch = GenerateBatch::query()->create([
                'template_id' => $template->id,
                'requested_by' => $user->id,
                'institution_id' => $institutionId,
                'status' => 'processing',
                'total_cards' => $students->count(),
                'success_count' => 0,
                'failed_count' => 0,
                'started_at' => now(),
                'options_json' => $options,
            ]);

            foreach ($students as $student) {
                $generatedCard = GeneratedCard::query()->create([
                    'batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'template_id' => $template->id,
                    'status' => 'pending',
                ]);

                RenderGeneratedCardJob::dispatch($generatedCard->id);
            }
            return $batch;
        });
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'generate_batch.create',
            subject: $batch,
            request: $request,
            metadata: [
                'template_id' => $template->id,
                'total_cards' => $students->count(),
            ],
        );

        return back()->with('status', 'Generate batch queued.');
    }

    public function downloadA4Pdf(Request $request, GenerateBatch $generateBatch, BatchPdfService $batchPdfService): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->ensureInstitutionAccess($user, $generateBatch->institution_id);

        $mediaAsset = $this->resolveOrGenerateBatchPdf($generateBatch, $user, $batchPdfService);
        app(ActivityLogService::class)->write(
            actor: $user,
            action: 'generate_batch.download_a4_pdf',
            subject: $generateBatch,
            request: $request,
            metadata: [
                'media_asset_id' => $mediaAsset->id,
            ],
        );

        if ($request->expectsJson() || $request->boolean('as_json')) {
            return response()->json([
                'media_asset_id' => $mediaAsset->id,
                'stream_download_url' => route('media-assets.stream', $mediaAsset),
                'filename' => $mediaAsset->original_name ?: sprintf('batch-%d-a4.pdf', $generateBatch->id),
            ]);
        }

        return redirect()->route('media-assets.download', $mediaAsset);
    }

    protected function resolveOrGenerateBatchPdf(GenerateBatch $batch, User $user, BatchPdfService $batchPdfService): MediaAsset
    {
        $options = is_array($batch->options_json) ? $batch->options_json : [];
        $existingId = $options['a4_pdf_media_id'] ?? null;

        if (is_int($existingId) || ctype_digit((string) $existingId)) {
            $existing = MediaAsset::query()->find((int) $existingId);

            if ($existing && $this->canAccessMediaAsset($user, $existing)) {
                return $existing;
            }
        }

        $mediaAsset = $batchPdfService->generateAndStore($batch, $user);
        $options['a4_pdf_media_id'] = $mediaAsset->id;
        $batch->update(['options_json' => $options]);

        return $mediaAsset;
    }

    protected function a4PdfDownloadUrl(GenerateBatch $batch): ?string
    {
        if (! in_array($batch->status, ['done', 'failed'], true)) {
            return null;
        }

        return route('generate-batches.download-a4', $batch);
    }

    protected function a4PdfResolveUrl(GenerateBatch $batch): ?string
    {
        if (! in_array($batch->status, ['done', 'failed'], true)) {
            return null;
        }

        return route('generate-batches.download-a4', [
            'generateBatch' => $batch,
            'as_json' => 1,
        ]);
    }
}
