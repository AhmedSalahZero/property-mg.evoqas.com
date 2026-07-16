<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerTitle extends Model
{
    protected $fillable = [
        'company_id',
        'manpower_department_id',
        'title_name',
        'cost_center',
        'sort_order',
    ];

    // is_branch_title kept in DB for backward compatibility but always false going forward.
    // Not exposed in fillable — controller hardcodes it to false on create.

    public function department()
    {
        return $this->belongsTo(ManpowerDepartment::class, 'manpower_department_id');
    }
}
