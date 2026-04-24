<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveInstitutionRequest;
use App\Models\Institution;
use App\Models\MediaAsset;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $institutions = $this->visibleInstitutions($user)
            ->load(['logoMedia', 'stampMedia', 'leaderSignatureMedia']);
        $brandingAssets = $this->visibleMediaAssets($user)
            ->whereIn('category', ['institution_logo', 'institution_stamp', 'institution_signature'])
            ->values();

        return Inertia::render('Institutions/Index', [
            'institutions' => $institutions->map(fn (Institution $institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
                'npsn' => $institution->npsn,
                'address' => $institution->address,
                'village' => $institution->village,
                'district' => $institution->district,
                'regency' => $institution->regency,
                'province' => $institution->province,
                'postal_code' => $institution->postal_code,
                'phone' => $institution->phone,
                'email' => $institution->email,
                'website' => $institution->website,
                'leader_name' => $institution->leader_name,
                'leader_nip' => $institution->leader_nip,
                'leader_title' => $institution->leader_title,
                'logo_media_id' => $institution->logo_media_id,
                'stamp_media_id' => $institution->stamp_media_id,
                'leader_signature_media_id' => $institution->leader_signature_media_id,
                'logo_media_label' => $institution->logoMedia?->original_name,
                'stamp_media_label' => $institution->stampMedia?->original_name,
                'leader_signature_media_label' => $institution->leaderSignatureMedia?->original_name,
            ])->values(),
            'brandingAssets' => $brandingAssets->map(fn (MediaAsset $mediaAsset): array => [
                'id' => $mediaAsset->id,
                'owner_id' => $mediaAsset->owner_id,
                'category' => $mediaAsset->category,
                'label' => sprintf('#%d %s', $mediaAsset->id, $mediaAsset->original_name ?? $mediaAsset->object_key),
            ])->values(),
            'canCreate' => $user->isAdmin(),
            'canDelete' => $user->isAdmin(),
        ]);
    }

    public function store(SaveInstitutionRequest $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validated();
        $data['logo_media_id'] = null;
        $data['stamp_media_id'] = null;
        $data['leader_signature_media_id'] = null;

        $institution = Institution::query()->create($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'institution.create',
            subject: $institution,
            request: $request,
        );

        return back()->with('status', 'Institution created.');
    }

    public function update(SaveInstitutionRequest $request, Institution $institution): RedirectResponse
    {
        $this->ensureInstitutionAccess($request->user(), $institution->id);
        $data = $request->validated();
        $this->assertBrandingAssetsBelongToInstitution($institution, $data);
        $institution->update($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'institution.update',
            subject: $institution,
            request: $request,
        );

        return back()->with('status', 'Institution updated.');
    }

    public function destroy(Institution $institution): RedirectResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);
        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'institution.delete',
            subject: $institution,
            request: request(),
        );

        $institution->delete();

        return back()->with('status', 'Institution deleted.');
    }

    protected function assertBrandingAssetsBelongToInstitution(Institution $institution, array $data): void
    {
        $pairs = [
            'logo_media_id' => 'institution_logo',
            'stamp_media_id' => 'institution_stamp',
            'leader_signature_media_id' => 'institution_signature',
        ];

        foreach ($pairs as $field => $category) {
            if (! isset($data[$field]) || $data[$field] === null) {
                continue;
            }

            $asset = MediaAsset::query()->findOrFail($data[$field]);

            abort_unless(
                $asset->owner_type === 'institution'
                && $asset->owner_id === $institution->id
                && $asset->category === $category,
                422,
            );
        }
    }
}
