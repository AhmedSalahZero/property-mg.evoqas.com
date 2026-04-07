<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExpenseCategory extends Model {
    protected $fillable = ['company_id','category_name','cost_center','sort_order'];
    public function company() { return $this->belongsTo(Company::class); }
    public function items() { return $this->hasMany(ExpenseItem::class); }
}