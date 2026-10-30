<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Team management: admins add staff, set their role and switch access on or off.
 */
class StaffController extends Controller
{
    public function index()
    {
        $today = today()->toDateString();

        $staff = User::query()
            ->withCount('collections')
            ->withSum(['collections as collected_today' => fn ($query) => $query
                ->where('type', Transaction::TYPE_DEPOSIT)
                ->where('date', $today)], 'amount')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role', 'is_active', 'last_login_at', 'created_at']);

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
        ]);
    }

    public function create()
    {
        return Inertia::render('Staff/Create', [
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules() + [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);

        return redirect()->route('staff.index')->with('success', "{$user->name} has been added to the team.");
    }

    public function edit(User $user)
    {
        return Inertia::render('Staff/Edit', [
            'member' => $user->only('id', 'name', 'email', 'phone', 'role', 'is_active'),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate($this->rules($user) + [
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // The editor is always an active admin, so this alone guarantees the team keeps one.
        if ($user->is($request->user()) && ($validated['role'] !== User::ROLE_ADMIN || ! $validated['is_active'])) {
            return back()->with('error', 'You cannot remove your own admin access or deactivate yourself.');
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('staff.index')->with('success', "{$user->name}'s details have been updated.");
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => 'nullable|string|max:20',
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => 'required|boolean',
        ];
    }
}
