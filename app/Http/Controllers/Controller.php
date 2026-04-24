<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\Classroom;
use App\Models\GeneratedCard;
use App\Models\Institution;
use App\Models\MediaAsset;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class Controller
{
    protected function scopeByInstitution(Builder $query, ?User $user, string $column = 'institution_id'): Builder
    {
        if ($user?->isGuru()) {
            $query->where($column, $user->institution_id);
        }

        return $query;
    }

    protected function visibleInstitutions(User $user): Collection
    {
        return $this->scopeByInstitution(Institution::query(), $user, 'id')
            ->orderBy('name')
            ->get();
    }

    protected function visibleTemplateQuery(User $user): Builder
    {
        return CardTemplate::query()
            ->when(
                $user->isGuru(),
                fn (Builder $query): Builder => $query->where(function (Builder $builder) use ($user): void {
                    $builder
                        ->whereNull('institution_id')
                        ->orWhere('institution_id', $user->institution_id);
                }),
            );
    }

    protected function visibleMediaAssets(User $user): Collection
    {
        return MediaAsset::query()
            ->with(['owner', 'uploader'])
            ->latest()
            ->get()
            ->filter(fn (MediaAsset $mediaAsset): bool => $this->canAccessMediaAsset($user, $mediaAsset))
            ->values();
    }

    protected function ensureInstitutionAccess(User $user, ?int $institutionId): void
    {
        abort_unless($user->isAdmin() || $user->belongsToInstitution($institutionId), 403);
    }

    protected function institutionIdForModel(?Model $model): ?int
    {
        return match (true) {
            $model instanceof Institution => $model->id,
            $model instanceof Classroom => $model->institution_id,
            $model instanceof Student => $model->institution_id,
            $model instanceof CardTemplate => $model->institution_id,
            $model instanceof GeneratedCard => $model->batch?->institution_id ?? $model->student?->institution_id,
            default => null,
        };
    }

    protected function canAccessMediaAsset(User $user, MediaAsset $mediaAsset): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($mediaAsset->owner_type === null && $mediaAsset->uploaded_by === $user->id) {
            return true;
        }

        $owner = $mediaAsset->owner;

        if ($owner instanceof GeneratedCard) {
            $owner->loadMissing(['batch', 'student']);
        }

        if ($owner instanceof CardTemplate && $owner->institution_id === null) {
            return true;
        }

        return $user->belongsToInstitution($this->institutionIdForModel($owner));
    }
}
