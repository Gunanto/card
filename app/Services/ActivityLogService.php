<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\GeneratedCard;
use App\Models\GenerateBatch;
use App\Models\Institution;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function write(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $institutionId = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'institution_id' => $this->resolveInstitutionId($actor, $subject, $institutionId),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata_json' => $metadata !== [] ? $metadata : null,
        ]);
    }

    protected function resolveInstitutionId(?User $actor, ?Model $subject, ?int $override): ?int
    {
        if ($override !== null) {
            return $override;
        }

        if ($subject instanceof Institution) {
            return $subject->id;
        }

        if ($subject instanceof GenerateBatch) {
            return $subject->institution_id;
        }

        if ($subject instanceof GeneratedCard) {
            $subject->loadMissing(['batch', 'student']);

            return $subject->batch?->institution_id ?? $subject->student?->institution_id;
        }

        if ($subject instanceof MediaAsset) {
            if ($subject->owner_type === null) {
                return $actor?->institution_id;
            }

            $owner = $subject->owner;

            if ($owner instanceof Model) {
                return $this->resolveInstitutionId($actor, $owner, null);
            }
        }

        $institutionFromSubject = data_get($subject, 'institution_id');
        if (is_int($institutionFromSubject)) {
            return $institutionFromSubject;
        }

        if (is_numeric($institutionFromSubject)) {
            return (int) $institutionFromSubject;
        }

        return $actor?->institution_id;
    }
}
