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
use Illuminate\Support\Facades\Storage;
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

        $dispatchCardIds = [];

        $batch = DB::transaction(function () use ($students, $template, $user, $institutionId, $options, &$dispatchCardIds): GenerateBatch {
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

                $dispatchCardIds[] = (int) $generatedCard->id;
            }
            return $batch;
        });

        foreach ($dispatchCardIds as $generatedCardId) {
            RenderGeneratedCardJob::dispatch($generatedCardId)->afterCommit();
        }

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

    public function destroy(Request $request, GenerateBatch $generateBatch): RedirectResponse
    {
        $user = $request->user();
        $this->ensureInstitutionAccess($user, $generateBatch->institution_id);
        abort_unless(in_array($generateBatch->status, ['done', 'failed'], true), 422, 'Batch masih berjalan dan belum bisa dihapus.');

        DB::transaction(function () use ($generateBatch): void {
            $generateBatch->loadMissing('generatedCards');
            $generatedCardIds = $generateBatch->generatedCards->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $mediaAssets = $this->mediaAssetsForBatch($generateBatch, $generatedCardIds);

            foreach ($mediaAssets as $mediaAsset) {
                $this->deleteMediaAssetObject($mediaAsset);
                $mediaAsset->delete();
            }

            $generateBatch->delete();
        });

        app(ActivityLogService::class)->write(
            actor: $user,
            action: 'generate_batch.delete',
            subject: $generateBatch,
            request: $request,
        );

        return back()->with('status', 'Batch deleted.');
    }

    protected function resolveOrGenerateBatchPdf(GenerateBatch $batch, User $user, BatchPdfService $batchPdfService): MediaAsset
    {
        $options = is_array($batch->options_json) ? $batch->options_json : [];
        // Always regenerate A4 so the output follows latest rendered cards/template changes.
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

    protected function mediaAssetsForBatch(GenerateBatch $batch, array $generatedCardIds)
    {
        $ids = GeneratedCard::query()
            ->whereIn('id', $generatedCardIds)
            ->whereNotNull('front_media_id')
            ->pluck('front_media_id')
            ->merge(
                GeneratedCard::query()
                    ->whereIn('id', $generatedCardIds)
                    ->whereNotNull('back_media_id')
                    ->pluck('back_media_id'),
            )
            ->merge(
                GeneratedCard::query()
                    ->whereIn('id', $generatedCardIds)
                    ->whereNotNull('pdf_media_id')
                    ->pluck('pdf_media_id'),
            )
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        $batchA4AssetId = data_get($batch->options_json, 'a4_pdf_media_id');
        if (is_numeric($batchA4AssetId)) {
            $ids->push((int) $batchA4AssetId);
        }

        $ownerAssets = MediaAsset::query()
            ->where(function ($query) use ($batch, $generatedCardIds): void {
                $query
                    ->where(function ($ownerQuery) use ($generatedCardIds): void {
                        if ($generatedCardIds === []) {
                            $ownerQuery->whereRaw('1=0');

                            return;
                        }

                        $ownerQuery
                            ->where('owner_type', 'generated_card')
                            ->whereIn('owner_id', $generatedCardIds);
                    })
                    ->orWhere(function ($ownerQuery) use ($batch): void {
                        $ownerQuery
                            ->where('owner_type', GenerateBatch::class)
                            ->where('owner_id', $batch->id);
                    });
            })
            ->pluck('id');

        return MediaAsset::query()
            ->whereIn('id', $ids->merge($ownerAssets)->unique()->values()->all())
            ->get();
    }

    protected function deleteMediaAssetObject(MediaAsset $mediaAsset): void
    {
        try {
            Storage::disk($mediaAsset->disk)->delete($mediaAsset->object_key);
        } catch (\Throwable) {
            // noop: deleting DB record is enough to hide stale references from UI.
        }
    }
}
