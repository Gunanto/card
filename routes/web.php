<?php

use App\Http\Controllers\CardTemplateController;
use App\Http\Controllers\ClassroomController as ClassroomHttpController;
use App\Http\Controllers\GenerateBatchController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController as StudentHttpController;
use App\Http\Controllers\UserManagementController;
use App\Models\CardTemplate;
use App\Models\Classroom;
use App\Models\GenerateBatch;
use App\Models\Institution;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (): RedirectResponse|Response {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'appName' => config('app.name'),
    ]);
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    $institutionScope = $user?->isGuru() ? $user->institution_id : null;

    return Inertia::render('Dashboard', [
        'stats' => [
            'institutions' => Institution::query()
                ->when($institutionScope !== null, fn ($query) => $query->where('id', $institutionScope))
                ->count(),
            'classrooms' => Classroom::query()
                ->when($institutionScope !== null, fn ($query) => $query->where('institution_id', $institutionScope))
                ->count(),
            'students' => Student::query()
                ->when($institutionScope !== null, fn ($query) => $query->where('institution_id', $institutionScope))
                ->count(),
            'templates' => CardTemplate::query()
                ->when(
                    $institutionScope !== null,
                    fn ($query) => $query->where(fn ($builder) => $builder->whereNull('institution_id')->orWhere('institution_id', $institutionScope)),
                )
                ->count(),
            'batches' => GenerateBatch::query()
                ->when($institutionScope !== null, fn ($query) => $query->where('institution_id', $institutionScope))
                ->count(),
        ],
        'scope' => [
            'role' => $user?->role,
            'institution_id' => $user?->institution_id,
        ],
    ]);
})->middleware(['auth', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/institutions', [InstitutionController::class, 'index'])->name('institutions.index');
    Route::post('/institutions', [InstitutionController::class, 'store'])->name('institutions.store');
    Route::put('/institutions/{institution}', [InstitutionController::class, 'update'])->name('institutions.update');
    Route::delete('/institutions/{institution}', [InstitutionController::class, 'destroy'])->name('institutions.destroy');

    Route::get('/classrooms', [ClassroomHttpController::class, 'index'])->name('classrooms.index');
    Route::post('/classrooms', [ClassroomHttpController::class, 'store'])->name('classrooms.store');
    Route::put('/classrooms/{classroom}', [ClassroomHttpController::class, 'update'])->name('classrooms.update');
    Route::delete('/classrooms/{classroom}', [ClassroomHttpController::class, 'destroy'])->name('classrooms.destroy');

    Route::get('/students', [StudentHttpController::class, 'index'])->name('students.index');
    Route::post('/students', [StudentHttpController::class, 'store'])->name('students.store');
    Route::put('/students/{student}', [StudentHttpController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentHttpController::class, 'destroy'])->name('students.destroy');

    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports/students', [ImportController::class, 'store'])->name('imports.students.store');

    Route::get('/media-assets', [MediaAssetController::class, 'index'])->name('media-assets.index');
    Route::post('/media-assets', [MediaAssetController::class, 'store'])->name('media-assets.store');
    Route::get('/media-assets/{mediaAsset}/temporary-url', [MediaAssetController::class, 'temporaryUrl'])->name('media-assets.temporary-url');
    Route::get('/media-assets/{mediaAsset}/download', [MediaAssetController::class, 'download'])->name('media-assets.download');

    Route::get('/card-templates', [CardTemplateController::class, 'index'])->name('card-templates.index');
    Route::post('/card-templates', [CardTemplateController::class, 'store'])->name('card-templates.store');
    Route::put('/card-templates/{cardTemplate}', [CardTemplateController::class, 'update'])->name('card-templates.update');
    Route::delete('/card-templates/{cardTemplate}', [CardTemplateController::class, 'destroy'])->name('card-templates.destroy');

    Route::get('/generate-batches', [GenerateBatchController::class, 'index'])->name('generate-batches.index');
    Route::post('/generate-batches', [GenerateBatchController::class, 'store'])->name('generate-batches.store');
    Route::get('/generate-batches/{generateBatch}/download-a4', [GenerateBatchController::class, 'downloadA4Pdf'])->name('generate-batches.download-a4');

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
