<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaAssetRequest;
use App\Models\CardTemplate;
use App\Models\Institution;
use App\Models\MediaAsset;
use App\Models\Student;
use App\Services\ActivityLogService;
use App\Services\MediaAssetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class MediaAssetController extends Controller
{
    public function __construct(protected MediaAssetService $mediaAssetService)
    {
    }

    public function index(): Response
    {
        $user = request()->user();
        $assets = $this->visibleMediaAssets($user);
        $institutions = $this->visibleInstitutions($user);
        $students = $this->scopeByInstitution(
            Student::query()->orderBy('name'),
            $user,
        )->get();
        $templates = $this->visibleTemplateQuery($user)->orderBy('name')->get();

        return Inertia::render('MediaAssets/Index', [
            'assets' => $assets->map(fn (MediaAsset $mediaAsset): array => [
                'id' => $mediaAsset->id,
                'category' => $mediaAsset->category,
                'category_label' => MediaAsset::CATEGORY_LABELS[$mediaAsset->category] ?? $mediaAsset->category,
                'owner_type' => $mediaAsset->owner_type,
                'owner_id' => $mediaAsset->owner_id,
                'owner_label' => $this->ownerLabel($mediaAsset),
                'original_name' => $mediaAsset->original_name,
                'mime_type' => $mediaAsset->mime_type,
                'size_bytes' => $mediaAsset->size_bytes,
                'width' => $mediaAsset->width,
                'height' => $mediaAsset->height,
                'created_at' => $mediaAsset->created_at?->toDateTimeString(),
                'download_url' => route('media-assets.download', $mediaAsset),
                'stream_download_url' => route('media-assets.stream', $mediaAsset),
                'temporary_url_endpoint' => route('media-assets.temporary-url', $mediaAsset),
            ])->values(),
            'categories' => collect(MediaAsset::USER_UPLOAD_CATEGORIES)
                ->map(fn (string $value): array => [
                    'value' => $value,
                    'label' => MediaAsset::CATEGORY_LABELS[$value] ?? $value,
                ])->values(),
            'owners' => [
                'institution' => $institutions->map(fn (Institution $institution): array => [
                    'id' => $institution->id,
                    'name' => $institution->name,
                ])->values(),
                'student' => $students->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => sprintf('%s (%s)', $student->name, $student->student_code),
                ])->values(),
                'card_template' => $templates
                    ->whereNotNull('institution_id')
                    ->map(fn (CardTemplate $template): array => [
                        'id' => $template->id,
                        'name' => $template->name,
                    ])->values(),
            ],
        ]);
    }

    public function store(StoreMediaAssetRequest $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request->input('owner_type'), (int) $request->integer('owner_id'));
        $institutionId = $this->institutionIdForModel($owner);

        if ($owner instanceof CardTemplate && $owner->institution_id === null) {
            abort_unless($request->user()->isAdmin(), 403);
        } else {
            $this->ensureInstitutionAccess($request->user(), $institutionId);
        }

        $asset = $this->mediaAssetService->storeUploadedFile(
            $request->file('file'),
            $request->string('category')->toString(),
            $owner,
            $request->user(),
            $this->pathPrefixFor($owner, $request->string('category')->toString()),
        );

        $this->syncOwnerReference($owner, $asset);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'media_asset.upload',
            subject: $asset,
            request: $request,
            metadata: [
                'category' => $asset->category,
            ],
        );

        return back()->with('status', sprintf('Media uploaded: #%d', $asset->id));
    }

    public function temporaryUrl(MediaAsset $mediaAsset): JsonResponse
    {
        abort_unless($this->canAccessMediaAsset(request()->user(), $mediaAsset), 403);

        return response()->json([
            'url' => $this->mediaAssetService->temporaryUrl($mediaAsset, now()->addMinutes(10)),
        ]);
    }

    public function download(MediaAsset $mediaAsset): RedirectResponse
    {
        abort_unless($this->canAccessMediaAsset(request()->user(), $mediaAsset), 403);
        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'media_asset.download',
            subject: $mediaAsset,
            request: request(),
        );

        return redirect()->away($this->mediaAssetService->temporaryUrl($mediaAsset, now()->addMinutes(10)));
    }

    public function stream(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        abort_unless($this->canAccessMediaAsset($request->user(), $mediaAsset), 403);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'media_asset.download_stream',
            subject: $mediaAsset,
            request: $request,
        );

        $stream = Storage::disk($mediaAsset->disk)->readStream($mediaAsset->object_key);

        if (! is_resource($stream)) {
            abort(404, 'File tidak ditemukan.');
        }

        $filename = $mediaAsset->original_name ?: basename($mediaAsset->object_key);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => $mediaAsset->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) max(0, (int) $mediaAsset->size_bytes),
        ]);
    }

    protected function resolveOwner(string $ownerType, int $ownerId): Model
    {
        return match ($ownerType) {
            'institution' => Institution::query()->findOrFail($ownerId),
            'student' => Student::query()->findOrFail($ownerId),
            'card_template' => CardTemplate::query()->findOrFail($ownerId),
            default => abort(422),
        };
    }

    protected function pathPrefixFor(Model $owner, string $category): string
    {
        return match ($category) {
            'institution_logo' => sprintf('institutions/%d/branding/logo', $owner->getKey()),
            'institution_stamp' => sprintf('institutions/%d/branding/stamp', $owner->getKey()),
            'institution_signature' => sprintf('institutions/%d/branding/signature', $owner->getKey()),
            'student_photo' => sprintf('students/%d/photos/original', $owner->getKey()),
            'template_background_front' => sprintf('templates/%d/background/front', $owner->getKey()),
            'template_background_back' => sprintf('templates/%d/background/back', $owner->getKey()),
            default => abort(422),
        };
    }

    protected function syncOwnerReference(Model $owner, MediaAsset $asset): void
    {
        if ($owner instanceof Institution) {
            $field = match ($asset->category) {
                'institution_logo' => 'logo_media_id',
                'institution_stamp' => 'stamp_media_id',
                'institution_signature' => 'leader_signature_media_id',
                default => null,
            };

            if ($field !== null) {
                $owner->update([$field => $asset->id]);
            }

            return;
        }

        if ($owner instanceof CardTemplate) {
            $field = match ($asset->category) {
                'template_background_front' => 'background_front_media_id',
                'template_background_back' => 'background_back_media_id',
                default => null,
            };

            if ($field !== null) {
                $owner->update([$field => $asset->id]);
            }
        }
    }

    protected function ownerLabel(MediaAsset $mediaAsset): string
    {
        return match ($mediaAsset->owner_type) {
            'institution' => $mediaAsset->owner?->name ?? 'Institution',
            'student' => $mediaAsset->owner?->name ?? 'Student',
            'card_template' => $mediaAsset->owner?->name ?? 'Template',
            'generated_card' => sprintf('Generated Card #%d', $mediaAsset->owner_id),
            default => '-',
        };
    }
}
