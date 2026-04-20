<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    // Max Company Admins allowed per company
    const MAX_COMPANY_ADMINS = 3;

    // ── Index — list users ──────────────────────────────────────────────────────
    public function index()
    {
        $authUser = Auth::user();

        if ($authUser->is_super_admin) {
            // Super Admin sees all non-super-admin users, grouped by company
            $users = User::with('company:id,name')
                ->where('is_super_admin', false)
                ->orderBy('company_id')
                ->orderBy('name')
                ->get();
        } else {
            // Company Admin sees only users in their own company
            abort_unless($authUser->role === 'company_admin', 403);

            $users = User::with('company:id,name')
                ->where('company_id', $authUser->company_id)
                ->where('is_super_admin', false)
                ->orderBy('name')
                ->get();
        }

        $formatted = $users->map(fn($u) => $this->formatUser($u));

        // For Super Admin: pass list of companies for the filter dropdown
        $companies = $authUser->is_super_admin
            ? Company::orderBy('name')->get(['id', 'name'])
            : collect();

        return Inertia::render('Users/Index', [
            'users'       => $formatted,
            'companies'   => $companies,
            'authRole'    => $authUser->is_super_admin ? 'super_admin' : $authUser->role,
            'myCompanyId' => $authUser->company_id,
        ]);
    }

    // ── Create form ─────────────────────────────────────────────────────────────
    public function create()
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        if ($authUser->is_super_admin) {
            $companies = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            // Company Admin can only add to their own company
            $companies = Company::where('id', $authUser->company_id)->get(['id', 'name']);
        }

        $userLimit = $authUser->is_super_admin ? null : $authUser->max_users;
        $userCount = $authUser->is_super_admin ? 0 : $authUser->companyUserCount();

        return Inertia::render('Users/Create', [
            'companies'   => $companies,
            'roles'       => $this->roleOptions($authUser),
            'authRole'    => $authUser->is_super_admin ? 'super_admin' : $authUser->role,
            'myCompanyId' => $authUser->company_id,
            'userLimit'   => $userLimit,
            'userCount'   => $userCount,
        ]);
    }

    // ── Store ───────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
            'role'       => ['required', Rule::in(['company_admin', 'manager', 'sales_manager', 'analyst', 'viewer'])],
            'job_title'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'is_active'  => 'boolean',
            'max_users'  => 'nullable|integer|min:1|max:9999',
        ]);

        // Company Admin can only add to their own company
        if (!$authUser->is_super_admin) {
            abort_unless((int) $data['company_id'] === $authUser->company_id, 403);

            if (!is_null($authUser->max_users)) {
                abort_if(
                    $authUser->companyUserCount() >= $authUser->max_users,
                    422,
                    'You have reached your user creation limit of ' . $authUser->max_users . ' users.'
                );
            }
        }

        // Enforce max 3 company_admins per company
        if ($data['role'] === 'company_admin') {
            $this->enforceAdminLimit($data['company_id']);
        }

        User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'company_id' => $data['company_id'],
            'role'       => $data['role'],
            'job_title'  => $data['job_title'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'max_users'  => ($authUser->is_super_admin && ($data['role'] ?? '') === 'company_admin')
                ? ($data['max_users'] ?? null)
                : null,
        ]);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    // ── Edit form ───────────────────────────────────────────────────────────────
    public function edit(User $user)
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        // Company Admin can only edit users in their own company
        if (!$authUser->is_super_admin) {
            abort_unless($user->company_id === $authUser->company_id, 403);
        }

        if ($authUser->is_super_admin) {
            $companies = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            $companies = Company::where('id', $authUser->company_id)->get(['id', 'name']);
        }

        return Inertia::render('Users/Edit', [
            'user'        => $this->formatUser($user),
            'companies'   => $companies,
            'roles'       => $this->roleOptions($authUser),
            'authRole'    => $authUser->is_super_admin ? 'super_admin' : $authUser->role,
            'myCompanyId' => $authUser->company_id,
        ]);
    }

    // ── Update ──────────────────────────────────────────────────────────────────
    public function update(Request $request, User $user)
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        if (!$authUser->is_super_admin) {
            abort_unless($user->company_id === $authUser->company_id, 403);
        }

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => 'nullable|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
            'role'       => ['required', Rule::in(['company_admin', 'manager', 'sales_manager', 'analyst', 'viewer'])],
            'job_title'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'is_active'  => 'boolean',
            'max_users'  => 'nullable|integer|min:1|max:9999',
        ]);

        if (!$authUser->is_super_admin) {
            abort_unless((int) $data['company_id'] === $authUser->company_id, 403);
        }

        // If promoting to company_admin, check limit (exclude current user from count)
        if ($data['role'] === 'company_admin' && $user->role !== 'company_admin') {
            $this->enforceAdminLimit($data['company_id']);
        }

        $updateData = [
            'name'       => $data['name'],
            'email'      => $data['email'],
            'company_id' => $data['company_id'],
            'role'       => $data['role'],
            'job_title'  => $data['job_title'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if ($authUser->is_super_admin) {
            $updateData['max_users'] = ($data['role'] === 'company_admin')
                ? ($data['max_users'] ?? null)
                : null;
        }

        $user->update($updateData);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    // ── Destroy ─────────────────────────────────────────────────────────────────
    public function destroy(User $user)
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        if (!$authUser->is_super_admin) {
            abort_unless($user->company_id === $authUser->company_id, 403);
        }

        // Prevent self-deletion
        abort_if($user->id === $authUser->id, 403, 'You cannot delete your own account.');

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted.');
    }

    // ── Toggle Active ───────────────────────────────────────────────────────────
    public function toggleActive(User $user)
    {
        $authUser = Auth::user();
        abort_unless($authUser->is_super_admin || $authUser->role === 'company_admin', 403);

        if (!$authUser->is_super_admin) {
            abort_unless($user->company_id === $authUser->company_id, 403);
        }

        abort_if($user->id === $authUser->id, 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', $user->fresh()->is_active ? 'User activated.' : 'User deactivated.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function enforceAdminLimit(int $companyId): void
    {
        $adminCount = User::where('company_id', $companyId)
            ->where('role', 'company_admin')
            ->count();

        abort_if(
            $adminCount >= self::MAX_COMPANY_ADMINS,
            422,
            'This company already has the maximum of ' . self::MAX_COMPANY_ADMINS . ' Company Admins.'
        );
    }

    private function roleOptions(User $authUser): array
    {
        return [
            ['value' => 'company_admin',  'label' => 'Company Admin',
             'description' => 'Full access + can manage users (max 3 per company)'],
            ['value' => 'manager',        'label' => 'Manager',
             'description' => 'Full access to all modules, cannot manage users'],
            ['value' => 'sales_manager',  'label' => 'Sales Manager',
             'description' => 'Owns contract pipeline, view contracts & revenue'],
            ['value' => 'analyst',        'label' => 'Analyst',
             'description' => 'Can view and enter data, cannot delete or change settings'],
            ['value' => 'viewer',         'label' => 'Viewer',
             'description' => 'Read-only access to results and reports'],
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'job_title'  => $user->job_title,
            'phone'      => $user->phone,
            'is_active'  => $user->is_active,
            'company_id' => $user->company_id,
            'max_users'  => $user->max_users,
            'company'    => $user->company ? ['id' => $user->company->id, 'name' => $user->company->name] : null,
            'created_at' => $user->created_at?->format('d M Y'),
        ];
    }
}