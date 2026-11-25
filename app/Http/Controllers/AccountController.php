<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Account management. Every route here is behind the "administer" gate. */
class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = User::query()
            ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->card($user, $request->user()))
            ->values();

        return response()->json([
            'accounts' => $accounts,
            'counts' => [
                'active' => User::query()->count(),
                'deleted' => User::onlyTrashed()->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate($this->rules($request));

        $user = User::create([
            'name' => $attributes['name'],
            'username' => $attributes['username'],
            'password' => $attributes['password'],
            'role' => $attributes['role'],
        ]);

        return response()->json($this->card($user, $request->user()), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $attributes = $request->validate($this->rules($request, $user));

        if ($user->is($request->user()) && $attributes['role'] !== $user->role) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        $user->fill([
            'name' => $attributes['name'],
            'username' => $attributes['username'],
            'role' => $attributes['role'],
        ]);

        if (! empty($attributes['password'])) {
            $user->password = $attributes['password'];
        }

        $user->save();

        return response()->json($this->card($user, $request->user()));
    }

    /** Soft delete: the account can no longer log in, but an administrator can restore it. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot delete your own account.');

        $user->delete();

        return response()->json(['deleted' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return response()->json(['restored' => true]);
    }

    private function rules(Request $request, ?User $existing = null): array
    {
        // Usernames are stored in lower case so "Maria" and "maria" are the same login.
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'regex:/^[a-z0-9._-]{3,30}$/',
                // Checks every row, deleted accounts included, because the database column is unique.
                Rule::unique('users', 'username')->ignore($existing?->id),
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$existing ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ];
    }

    private function card(User $user, ?User $viewer): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'created' => $user->created_at?->format('M j, Y'),
            'deleted' => $user->deleted_at?->format('M j, Y'),
            'is_self' => $viewer !== null && $user->is($viewer),
        ];
    }
}
