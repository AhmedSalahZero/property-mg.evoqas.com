<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'is_super_admin',
        'is_active',
        'role',         // company_admin | manager | analyst | viewer
        'phone',
        'job_title',
        'avatar',
        'theme',
        'max_users',   // only used when role = company_admin
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
            'is_active'         => 'boolean',
            'max_users'         => 'integer',
        ];
    }

    /**
     * Send the password reset notification with branded email template.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ── Role helpers ───────────────────────────────────────────────────────────

    public function isCompanyAdmin(): bool
    {
        return $this->role === 'company_admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isSalesManager(): bool
    {
        return $this->role === 'sales_manager';
    }

    public function isAnalyst(): bool
    {
        return $this->role === 'analyst';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    /**
     * Can this user manage (create/edit/delete) other users?
     * Super Admin always can. Company Admin can within their own company.
     */
    public function canManageUsers(): bool
    {
        return $this->is_super_admin || $this->role === 'company_admin';
    }

    /**
     * Can write data (upload, enter, save) — not delete or manage settings
     */
    public function canWrite(): bool
    {
        return $this->is_super_admin
            || in_array($this->role, ['company_admin', 'manager', 'sales_manager', 'analyst']);
    }

    /**
     * Can delete records or change module settings
     */
    public function canDelete(): bool
    {
        return $this->is_super_admin
            || in_array($this->role, ['company_admin', 'manager']);
    }

    /**
     * Has at least viewer-level access (i.e. any valid role set)
     */
    public function hasCompanyAccess(int $companyId): bool
    {
        if ($this->is_super_admin) return true;
        return $this->company_id === $companyId && $this->is_active;
    }

    /**
     * How many non-super-admin users currently exist in this admin's company.
     * Used to enforce max_users for company_admin role.
     */
    public function companyUserCount(): int
    {
        if (!$this->company_id) return 0;
        return User::where('company_id', $this->company_id)
            ->where('is_super_admin', false)
            ->count();
    }

    /**
     * Whether this company_admin has reached their user creation limit.
     * Returns false when max_users is NULL (no limit set).
     */
    public function isAtUserLimit(): bool
    {
        if (is_null($this->max_users)) return false;
        return $this->companyUserCount() >= $this->max_users;
    }
}