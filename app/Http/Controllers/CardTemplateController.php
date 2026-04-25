<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCardTemplateRequest;
use App\Models\CardTemplate;
use App\Models\CardType;
use App\Models\MediaAsset;
use App\Services\ActivityLogService;
use App\Support\DefaultCardTemplateData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CardTemplateController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $institutions = $this->visibleInstitutions($user);
        $cardTypes = CardType::query()->orderBy('name')->get();
        $templates = $this->visibleTemplateQuery($user)
            ->with(['institution', 'cardType', 'backgroundFrontMedia', 'backgroundBackMedia'])
            ->orderBy('name')
            ->get();
        $backgroundAssets = $this->visibleMediaAssets($user)
            ->whereIn('category', ['template_background_front', 'template_background_back'])
            ->values();

        return Inertia::render('CardTemplates/Index', [
            'templates' => $templates->map(fn (CardTemplate $template): array => [
                'id' => $template->id,
                'institution_id' => $template->institution_id,
                'institution_name' => $template->institution?->name ?? 'Global',
                'card_type_id' => $template->card_type_id,
                'card_type_name' => $template->cardType?->name,
                'name' => $template->name,
                'width_mm' => (float) $template->width_mm,
                'height_mm' => (float) $template->height_mm,
                'background_front_media_id' => $template->background_front_media_id,
                'background_back_media_id' => $template->background_back_media_id,
                'config_json' => $template->config_json,
                'print_layout_json' => $template->print_layout_json,
                'is_active' => $template->is_active,
            ])->values(),
            'institutions' => $institutions->map(fn ($institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])->values(),
            'cardTypes' => $cardTypes->map(fn (CardType $cardType): array => [
                'id' => $cardType->id,
                'name' => $cardType->name,
            ])->values(),
            'backgroundAssets' => $backgroundAssets->map(fn (MediaAsset $mediaAsset): array => [
                'id' => $mediaAsset->id,
                'owner_type' => $mediaAsset->owner_type,
                'owner_id' => $mediaAsset->owner_id,
                'institution_id' => $this->institutionIdForModel($mediaAsset->owner),
                'category' => $mediaAsset->category,
                'label' => sprintf('#%d %s', $mediaAsset->id, $mediaAsset->original_name ?? $mediaAsset->object_key),
                'stream_download_url' => route('media-assets.stream', $mediaAsset),
            ])->values(),
            'defaults' => [
                'config_json_text' => DefaultCardTemplateData::configJson(),
                'print_layout_json_text' => DefaultCardTemplateData::printLayoutJson(),
            ],
            'forcedInstitutionId' => $user->isGuru() ? $user->institution_id : null,
        ]);
    }

    public function store(SaveCardTemplateRequest $request): RedirectResponse
    {
        $data = $this->validatedPayload($request);
        $data['background_front_media_id'] = null;
        $data['background_back_media_id'] = null;

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        if ($data['institution_id'] !== null) {
            $this->ensureInstitutionAccess($request->user(), $data['institution_id']);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        $template = CardTemplate::query()->create($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'card_template.create',
            subject: $template,
            request: $request,
        );

        return back()->with('status', 'Template created.');
    }

    public function update(SaveCardTemplateRequest $request, CardTemplate $cardTemplate): RedirectResponse
    {
        if ($cardTemplate->institution_id !== null) {
            $this->ensureInstitutionAccess($request->user(), $cardTemplate->institution_id);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        $data = $this->validatedPayload($request);

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        if ($data['institution_id'] === null) {
            abort_unless($request->user()->isAdmin(), 403);
        }

        $this->assertTemplateBackgrounds($cardTemplate, $data);
        $cardTemplate->update($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'card_template.update',
            subject: $cardTemplate,
            request: $request,
        );

        return back()->with('status', 'Template updated.');
    }

    public function destroy(CardTemplate $cardTemplate): RedirectResponse
    {
        if ($cardTemplate->institution_id !== null) {
            $this->ensureInstitutionAccess(request()->user(), $cardTemplate->institution_id);
        } else {
            abort_unless(request()->user()->isAdmin(), 403);
        }

        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'card_template.delete',
            subject: $cardTemplate,
            request: request(),
        );

        $cardTemplate->delete();

        return back()->with('status', 'Template deleted.');
    }

    protected function validatedPayload(SaveCardTemplateRequest $request): array
    {
        $data = $request->validated();

        return [
            'institution_id' => $data['institution_id'] ?? null,
            'card_type_id' => $data['card_type_id'],
            'name' => $data['name'],
            'width_mm' => $data['width_mm'],
            'height_mm' => $data['height_mm'],
            'background_front_media_id' => $data['background_front_media_id'] ?? null,
            'background_back_media_id' => $data['background_back_media_id'] ?? null,
            'config_json' => $request->configPayload(),
            'print_layout_json' => $request->printLayoutPayload(),
            'is_active' => $data['is_active'],
        ];
    }

    protected function assertTemplateBackgrounds(CardTemplate $cardTemplate, array $data): void
    {
        $pairs = [
            'background_front_media_id' => 'template_background_front',
            'background_back_media_id' => 'template_background_back',
        ];

        foreach ($pairs as $field => $category) {
            if (! isset($data[$field]) || $data[$field] === null) {
                continue;
            }

            $asset = MediaAsset::query()->findOrFail($data[$field]);

            abort_unless(
                $asset->category === $category
                && (
                    ($asset->owner_type === 'card_template' && $asset->owner_id === $cardTemplate->id)
                    || (
                        $asset->owner_type === 'institution'
                        && $cardTemplate->institution_id !== null
                        && $asset->owner_id === $cardTemplate->institution_id
                    )
                ),
                422,
            );
        }
    }
}
