<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseItem extends Model
{
    protected $fillable = [
        'company_id',
        'expense_category_id',
        'item_name',
        'coa_code',
        'is_active',
        'sort_order',
        'is_employee_expense',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'is_employee_expense' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
