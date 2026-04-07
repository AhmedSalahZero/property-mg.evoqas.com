<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    // ── Fillable ─────────────────────────────────────────────────
    protected $fillable = [
        'name',
        'trade_name',
        'logo',
        'description',
        'notes',
        'parent_id',
        'legal_structure',
        'established_date',
        'established_year',
        'registration_number',
        'tax_id',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'website',
        'currency',
        'fiscal_year_start',
        'tax_type',
        'enabled_modules',
        'is_active',
    ];

    // ── Casts ────────────────────────────────────────────────────
    protected $casts = [
        'enabled_modules'  => 'array',
        'established_date' => 'date',
        'is_active'        => 'boolean',
    ];

    // ── Available Modules ────────────────────────────────────────
    public const MODULES = [
        'contract_analysis'    => 'Contract Analysis',
        'sales_analysis'       => 'Sales Analysis',
        'expenses_analysis'    => 'Expenses Analysis',
        'profitability'        => 'Profitability Analysis',
        // 'financial_statements' => 'Financial Statements', //to be postponded//
        // 'kpis'                 => 'KPI Dashboard', //to be postponded//
        'financial_studies'    => 'Financial Studies',
        'projects_tasks'       => 'Projects & Tasks',
        'statistica'           => 'Statistica',
        'loan_calculator'      => 'Loan Calculator',
        'customer_rating'      => 'Customer Rating',
        'price_calculator'     => 'Price Calculator',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(Company::class, 'parent_id');
    }

    public function subsidiaries()
    {
        return $this->hasMany(Company::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function userTasks()
    {
        return $this->hasMany(UserTask::class);
    }

    public function kpiDefinitions()
    {
        return $this->hasMany(KpiDefinition::class);
    }

    // ── Module Helpers ───────────────────────────────────────────

    // Check if a specific module is enabled for this company
    public function hasModule(string $module): bool
    {
        if (empty($this->enabled_modules)) return false;
        return in_array($module, $this->enabled_modules);
    }

    // Enable a module
    public function enableModule(string $module): void
    {
        $modules = $this->enabled_modules ?? [];
        if (!in_array($module, $modules)) {
            $modules[] = $module;
            $this->update(['enabled_modules' => $modules]);
        }
    }

    // Disable a module
    public function disableModule(string $module): void
    {
        $modules = $this->enabled_modules ?? [];
        $this->update([
            'enabled_modules' => array_values(array_filter(
                $modules, fn($m) => $m !== $module
            ))
        ]);
    }

    // Get all enabled module labels
    public function enabledModuleLabels(): array
    {
        $modules = $this->enabled_modules ?? [];
        return array_intersect_key(self::MODULES, array_flip($modules));
    }

    // ── Tax Helpers ──────────────────────────────────────────────

    public function usesCIT(): bool
    {
        return $this->tax_type === 'corporate_income_tax';
    }

    public function usesZakat(): bool
    {
        return $this->tax_type === 'zakat';
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}