<?php

namespace App\Providers;

use App\Models\CardTemplate;
use App\Models\GeneratedCard;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'institution' => Institution::class,
            'student' => Student::class,
            'card_template' => CardTemplate::class,
            'generated_card' => GeneratedCard::class,
        ]);

        Gate::before(function (User $user) {
            return $user->isAdmin() ? true : null;
        });

        Gate::define('admin-only', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-institution', function (User $user, ?int $institutionId = null): bool {
            if ($institutionId === null) {
                return $user->isGuru() && $user->institution_id !== null;
            }

            return $user->belongsToInstitution($institutionId);
        });

        Vite::prefetch(concurrency: 3);
    }
}
