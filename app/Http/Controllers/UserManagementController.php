<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserRequest;
use App\Models\Institution;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()->isAdmin(), 403);

        $users = User::query()
            ->with('institution')
            ->orderByDesc('id')
            ->get();
        $institutions = Institution::query()->orderBy('name')->get();

        return Inertia::render('Users/Index', [
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'institution_id' => $user->institution_id,
                'institution_name' => $user->institution?->name,
                'card_generation_limit' => $user->card_generation_limit,
            ])->values(),
            'institutions' => $institutions->map(fn (Institution $institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])->values(),
            'currentUserId' => request()->user()->id,
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validated();
        $data['institution_id'] = $data['role'] === 'guru' ? ($data['institution_id'] ?? null) : null;
        $data['card_generation_limit'] = $data['role'] === 'guru' ? ($data['card_generation_limit'] ?? null) : null;
        $data['email_verified_at'] = now();

        $user = User::query()->create($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'user.create',
            subject: $user,
            request: $request,
        );

        return back()->with('status', 'User created.');
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validated();
        $data['institution_id'] = $data['role'] === 'guru' ? ($data['institution_id'] ?? null) : null;
        $data['card_generation_limit'] = $data['role'] === 'guru' ? ($data['card_generation_limit'] ?? null) : null;

        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }

        $user->update($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'user.update',
            subject: $user,
            request: $request,
        );

        return back()->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless(request()->user()->isAdmin(), 403);
        abort_if($user->id === request()->user()->id, 422, 'Tidak bisa menghapus akun sendiri.');
        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'user.delete',
            subject: $user,
            request: request(),
        );

        $user->delete();

        return back()->with('status', 'User deleted.');
    }
}
