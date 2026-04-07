<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'category',
        'custom_category',
        'description',
        'amount',
        'expense_date',
        'receipt_path',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    /**
     * Returns the display label — custom_category overrides the enum label.
     */
    public function getCategoryLabelAttribute(): string
    {
        if (!empty($this->custom_category)) {
            return $this->custom_category;
        }
        return self::categoryLabel($this->category);
    }

    public static function categoryLabel(string $category): string
    {
        return [
            'consultant'        => 'Consultant',
            'freelancer'        => 'Freelancer',
            'legal'             => 'Legal Fees',
            'accounting'        => 'Accounting & Audit',
            'software'          => 'Software / IT',
            'saas_subscription' => 'SaaS Subscription',
            'hardware'          => 'Hardware / Equipment',
            'purchase'          => 'Purchase',
            'raw_materials'     => 'Raw Materials',
            'travel'            => 'Travel',
            'accommodation'     => 'Accommodation',
            'marketing'         => 'Marketing',
            'training'          => 'Training',
            'government_fees'   => 'Government Fees',
            'bank_charges'      => 'Bank Charges',
            'insurance'         => 'Insurance',
            'maintenance'       => 'Maintenance & Repair',
            'logistics'         => 'Logistics & Shipping',
            'other'             => 'Other',
        ][$category] ?? ucfirst($category);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}