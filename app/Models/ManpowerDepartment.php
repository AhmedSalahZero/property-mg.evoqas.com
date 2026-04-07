<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerDepartment extends Model
{
    protected $fillable = [
        'company_id',
        'department_name',
        'cost_center',
        'business_unit_name',
        'sort_order',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function titles()  { return $this->hasMany(ManpowerTitle::class); }
}