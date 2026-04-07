<?php

namespace App\Models;

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
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
            'is_active'         => 'boolean',
        ];
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
}