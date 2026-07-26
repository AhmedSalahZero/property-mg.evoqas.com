<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ManpowerDepartment;
use App\Models\ManpowerTitle;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\FixedAssetSetting;
use App\Models\PropertyCategory;
use App\Models\PropertyType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class CompanySettingsController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // MAIN PAGE — load all settings for a company
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        // Seed the 5 default property categories if this company has none yet
        PropertyCategory::seedDefaults($company->id);

        return Inertia::render('CompanySettings/Index', [
            'company' => $company,

            // ── Tenants ────────────────────────────────────────────── A→Z
            'tenants' => Customer::where('company_id', $company->id)
                ->orderBy('customer_name')
                ->orderBy('id')
                ->get(['id', 'customer_name', 'business_sector', 'tenant_nature', 'is_related_party', 'is_active']),

            // ── Manpower — HQ only (no branch titles) ────────────────  A→Z
            'departments' => ManpowerDepartment::where('company_id', $company->id)
                ->with(['titles' => fn($q) => $q->where('is_branch_title', false)->orderBy('title_name')->orderBy('id')])
                ->orderBy('department_name')
                ->orderBy('id')
                ->get(),

            // ── Costs & Expenses ─────────────────────────────────────  A→Z
            'expenseCategories' => ExpenseCategory::where('company_id', $company->id)
                ->with(['items' => fn($q) => $q->orderBy('item_name')->orderBy('id')])
                ->orderBy('category_name')
                ->orderBy('id')
                ->get(),

            // ── Fixed Assets ─────────────────────────────────────────  A→Z
            'fixedAssets' => FixedAssetSetting::where('company_id', $company->id)
                ->orderBy('asset_name')
                ->orderBy('id')
                ->get(),

            // ── Property Categories & Types ───────────────────────────  A→Z
            'propertyCategories' => PropertyCategory::where('company_id', $company->id)
                ->with(['types' => fn($q) => $q->orderBy('type_name')->orderBy('id')])
                ->orderBy('category_name')
                ->orderBy('id')
                ->get(),

            // ── Shared helpers ────────────────────────────────────────
            'costCenters' => $this->costCenters(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TENANTS — CRUD  (table stays "customers", display label is "Tenant")
    // ═══════════════════════════════════════════════════════════════════
    public function storeTenant(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'business_sector'  => 'nullable|string|max:150',
            'tenant_nature'    => 'nullable|in:individual,corporate',
            'is_related_party' => 'boolean',
        ]);

        $exists = Customer::where('company_id', $company->id)
            ->where('customer_name', $data['customer_name'])
            ->exists();

        if ($exists) {
            return back()->with('success', 'Tenant already exists.');
        }

        Customer::create([
            'company_id'       => $company->id,
            'customer_name'    => $data['customer_name'],
            // business_sector is cleared for individuals
            'business_sector'  => ($data['tenant_nature'] ?? null) === 'individual'
                                    ? null
                                    : ($data['business_sector'] ?? null),
            'tenant_nature'    => $data['tenant_nature'] ?? null,
            'is_related_party' => $data['is_related_party'] ?? false,
            'source'           => 'manual',
            'is_active'        => true,
            'sort_order'       => Customer::where('company_id', $company->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Tenant added.');
    }

    public function updateTenant(Request $request, Company $company, Customer $customer)
    {
        $this->authorizeCompany($company);
        abort_unless($customer->company_id === $company->id, 404);

        $data = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'business_sector'  => 'nullable|string|max:150',
            'tenant_nature'    => 'nullable|in:individual,corporate',
            'is_related_party' => 'boolean',
        ]);

        // business_sector is cleared server-side when nature is individual
        if (($data['tenant_nature'] ?? null) === 'individual') {
            $data['business_sector'] = null;
        }

        $customer->update($data);

        return back()->with('success', 'Tenant updated.');
    }

    public function destroyTenant(Company $company, Customer $customer)
    {
        $this->authorizeCompany($company);
        abort_unless($customer->company_id === $company->id, 404);
        $customer->delete();
        return back()->with('success', 'Tenant removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // MANPOWER — DEPARTMENTS
    // ═══════════════════════════════════════════════════════════════════
    public function storeDepartment(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'department_name'    => 'required|string|max:255',
            'cost_center'        => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
            'business_unit_name' => 'nullable|string|max:255',
        ]);

        ManpowerDepartment::create([
            'company_id'         => $company->id,
            'department_name'    => $data['department_name'],
            'cost_center'        => $data['cost_center'],
            'business_unit_name' => $data['business_unit_name'] ?? null,
            'sort_order'         => ManpowerDepartment::where('company_id', $company->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Company $company, ManpowerDepartment $department)
    {
        $this->authorizeCompany($company);
        abort_unless($department->company_id === $company->id, 404);

        $data = $request->validate([
            'department_name'    => 'required|string|max:255',
            'cost_center'        => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
            'business_unit_name' => 'nullable|string|max:255',
        ]);

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Company $company, ManpowerDepartment $department)
    {
        $this->authorizeCompany($company);
        abort_unless($department->company_id === $company->id, 404);
        $department->delete();
        return back()->with('success', 'Department removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // MANPOWER — TITLES
    // ═══════════════════════════════════════════════════════════════════
    public function storeTitle(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'title_name'              => 'required|string|max:255',
            'cost_center'             => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
            'manpower_department_id'  => 'required|exists:manpower_departments,id',
            'is_branch_title'         => 'boolean',
        ]);

        ManpowerTitle::create([
            'company_id'             => $company->id,
            'title_name'             => $data['title_name'],
            'cost_center'            => $data['cost_center'],
            'manpower_department_id' => $data['manpower_department_id'],
            'is_branch_title'        => $data['is_branch_title'] ?? false,
        ]);

        return back()->with('success', 'Title added.');
    }

    public function updateTitle(Request $request, Company $company, ManpowerTitle $title)
    {
        $this->authorizeCompany($company);
        abort_unless($title->company_id === $company->id, 404);

        $data = $request->validate([
            'title_name'  => 'required|string|max:255',
            'cost_center' => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
        ]);

        $title->update($data);

        return back()->with('success', 'Title updated.');
    }

    public function destroyTitle(Company $company, ManpowerTitle $title)
    {
        $this->authorizeCompany($company);
        abort_unless($title->company_id === $company->id, 404);
        $title->delete();
        return back()->with('success', 'Title removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // COSTS & EXPENSES — CATEGORIES
    // ═══════════════════════════════════════════════════════════════════
    public function storeExpenseCategory(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'category_name' => 'required|string|max:255',
            'cost_center'   => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
        ]);

        ExpenseCategory::create([
            'company_id'    => $company->id,
            'category_name' => $data['category_name'],
            'cost_center'   => $data['cost_center'],
            'sort_order'    => ExpenseCategory::where('company_id', $company->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Expense category added.');
    }

    public function updateExpenseCategory(Request $request, Company $company, ExpenseCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);

        $data = $request->validate([
            'category_name' => 'required|string|max:255',
            'cost_center'   => 'required|in:cost_of_service,opex,sales_marketing,admin_general',
        ]);

        $category->update($data);

        return back()->with('success', 'Expense category updated.');
    }

    public function destroyExpenseCategory(Company $company, ExpenseCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);
        $category->delete();
        return back()->with('success', 'Expense category removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // COSTS & EXPENSES — ITEMS
    // ═══════════════════════════════════════════════════════════════════
    public function storeExpenseItem(Request $request, Company $company, ExpenseCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);

        $data = $request->validate([
            'item_name'           => 'required|string|max:255',
            'coa_code'            => 'nullable|string|max:100',
            'is_employee_expense' => 'boolean',
        ]);

        ExpenseItem::create([
            'company_id'          => $company->id,
            'expense_category_id' => $category->id,
            'item_name'           => $data['item_name'],
            'coa_code'            => $data['coa_code'] ?? null,
            'is_employee_expense' => $data['is_employee_expense'] ?? false,
        ]);

        return back()->with('success', 'Expense item added.');
    }

    public function updateExpenseItem(Request $request, Company $company, ExpenseItem $item)
    {
        $this->authorizeCompany($company);
        abort_unless($item->company_id === $company->id, 404);

        $data = $request->validate([
            'item_name'           => 'required|string|max:255',
            'coa_code'            => 'nullable|string|max:100',
            'is_active'           => 'boolean',
            'is_employee_expense' => 'boolean',
        ]);

        $item->update($data);

        return back()->with('success', 'Expense item updated.');
    }

    public function destroyExpenseItem(Company $company, ExpenseItem $item)
    {
        $this->authorizeCompany($company);
        abort_unless($item->company_id === $company->id, 404);
        $item->delete();
        return back()->with('success', 'Expense item removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // FIXED ASSETS
    // ═══════════════════════════════════════════════════════════════════
    public function storeFixedAsset(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'asset_name'        => 'required|string|max:255',
            'is_employee_asset' => 'boolean',
        ]);

        FixedAssetSetting::create([
            'company_id'        => $company->id,
            'asset_name'        => $data['asset_name'],
            'asset_type'        => 'general',
            'is_employee_asset' => $data['is_employee_asset'] ?? false,
            'sort_order'        => FixedAssetSetting::where('company_id', $company->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Fixed asset added.');
    }

    public function updateFixedAsset(Request $request, Company $company, FixedAssetSetting $asset)
    {
        $this->authorizeCompany($company);
        abort_unless($asset->company_id === $company->id, 404);

        $data = $request->validate([
            'asset_name'        => 'required|string|max:255',
            'is_employee_asset' => 'boolean',
            'is_active'         => 'boolean',
        ]);

        $asset->update($data);

        return back()->with('success', 'Fixed asset updated.');
    }

    public function destroyFixedAsset(Company $company, FixedAssetSetting $asset)
    {
        $this->authorizeCompany($company);
        abort_unless($asset->company_id === $company->id, 404);
        $asset->delete();
        return back()->with('success', 'Fixed asset removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROPERTY CATEGORIES
    // ═══════════════════════════════════════════════════════════════════
    public function storePropertyCategory(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'category_name' => 'required|string|max:255',
        ]);

        $exists = PropertyCategory::where('company_id', $company->id)
            ->where('category_name', $data['category_name'])
            ->exists();

        if ($exists) {
            return back()->with('success', 'Category already exists.');
        }

        PropertyCategory::create([
            'company_id'    => $company->id,
            'category_name' => $data['category_name'],
            'is_system'     => false,
            'sort_order'    => PropertyCategory::where('company_id', $company->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Property category added.');
    }

    public function updatePropertyCategory(Request $request, Company $company, PropertyCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);

        $data = $request->validate([
            'category_name' => 'required|string|max:255',
        ]);

        $category->update($data);

        return back()->with('success', 'Property category updated.');
    }

    public function destroyPropertyCategory(Company $company, PropertyCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);

        if ($category->is_system) {
            return back()->with('error', 'System default categories cannot be deleted.');
        }

        $category->delete();
        return back()->with('success', 'Property category removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROPERTY TYPES
    // ═══════════════════════════════════════════════════════════════════
    public function storePropertyType(Request $request, Company $company, PropertyCategory $category)
    {
        $this->authorizeCompany($company);
        abort_unless($category->company_id === $company->id, 404);

        $data = $request->validate([
            'type_name' => 'required|string|max:255',
        ]);

        $exists = PropertyType::where('property_category_id', $category->id)
            ->where('type_name', $data['type_name'])
            ->exists();

        if ($exists) {
            return back()->with('success', 'Type already exists in this category.');
        }

        PropertyType::create([
            'company_id'           => $company->id,
            'property_category_id' => $category->id,
            'type_name'            => $data['type_name'],
            'sort_order'           => PropertyType::where('property_category_id', $category->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Property type added.');
    }

    public function updatePropertyType(Request $request, Company $company, PropertyType $type)
    {
        $this->authorizeCompany($company);
        abort_unless($type->company_id === $company->id, 404);

        $data = $request->validate([
            'type_name' => 'required|string|max:255',
        ]);

        $type->update($data);

        return back()->with('success', 'Property type updated.');
    }

    public function destroyPropertyType(Company $company, PropertyType $type)
    {
        $this->authorizeCompany($company);
        abort_unless($type->company_id === $company->id, 404);
        $type->delete();
        return back()->with('success', 'Property type removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    public function costCenters(): array
    {
        return [
            ['value' => 'cost_of_service', 'label' => 'Cost of Service'],
            ['value' => 'opex',            'label' => 'OPEX'],
            ['value' => 'sales_marketing', 'label' => 'Sales & Marketing'],
            ['value' => 'admin_general',   'label' => 'Administration & General'],
        ];
    }
}