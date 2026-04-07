<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use App\Models\RentCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RentContractController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // INDEX — all contracts for a property (3 tabs)
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $base = RentContract::forCompany($company->id)
            ->where('property_id', $property->id)
            ->with([
                'customer:id,customer_name,tenant_nature',
                'propertyUnit:id,unit_name,unit_code',
            ])
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('Contracts/Index', [
            'company'  => $company,
            'property' => $property->load([
                'propertyCategory:id,category_name',
                'propertyType:id,type_name',
                'units:id,property_id,unit_name,unit_code',
            ]),
            'running'    => $base->where('status', 'running')->values(),
            'expired'    => $base->where('status', 'expired')->values(),
            'terminated' => $base->where('status', 'terminated')->values(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════════
    public function create(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Contracts/Create', [
            'company'  => $company,
            'property' => $property->load([
                'units:id,property_id,unit_name,unit_code,is_active',
            ]),
            'tenants'             => $this->tenantsForCompany($company->id),
            'currencyOptions'     => $this->currencyOptions(),
            'intervalOptions'     => $this->intervalOptions(),
            'renewedFrom'         => null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $data = $this->validateContract($request, $company);

        // Auto-calculate insurance amount
        $basis           = !empty($data['min_monthly_rent']) && $data['min_monthly_rent'] > 0
                            ? $data['min_monthly_rent']
                            : $data['monthly_rent_amount'];
        $insuranceAmount = $basis * (int) ($data['insurance_months'] ?? 0);

        $contract = RentContract::create([
            'company_id'                 => $company->id,
            'property_id'                => $property->id,
            'property_unit_id'           => $data['property_unit_id'] ?? null,
            'revenue_type'               => $data['revenue_type'],
            'management_fee_rate'        => $data['management_fee_rate'] ?? null,
            'tenant_nature'              => $data['tenant_nature'],
            'customer_id'                => $data['customer_id'],
            'start_date'                 => $data['start_date'],
            'end_date'                   => $data['end_date'],
            'contract_currency'          => $data['contract_currency'],
            'monthly_rent_amount'        => $data['monthly_rent_amount'],
            'variable_revenue_pct'       => $data['variable_revenue_pct'] ?? null,
            'min_monthly_rent'           => $data['min_monthly_rent'] ?? null,
            'collection_currency'        => $data['collection_currency'],
            'collection_interval_months' => $data['collection_interval_months'],
            'insurance_months'           => $data['insurance_months'] ?? 0,
            'insurance_amount'           => $insuranceAmount,
            'annual_increase_rate'       => $data['annual_increase_rate'] ?? 0,
            'renewed_from_contract_id'   => $data['renewed_from_contract_id'] ?? null,
            'status'                     => 'running',
            'created_by'                 => auth()->id(),
        ]);

        // Generate rent revenues + collection schedule
        $contract->generateSchedules();

        return redirect()
            ->route('company.properties.contracts.index', [$company->id, $property->id])
            ->with('success', 'Contract created and schedule generated successfully.');
    }

     // ═══════════════════════════════════════════════════════════════════
    // EDIT
    // ═══════════════════════════════════════════════════════════════════

        public function edit(Company $company, Property $property, RentContract $contract)
            {
                $this->authorizeCompany($company);

                // Load necessary relationships + make sure all form fields are available
                $contract->load([
                    'customer:id,customer_name,tenant_nature',
                    'propertyUnit:id,unit_name,unit_code'   // optional but useful
                ]);

                return Inertia::render('Contracts/Create', [
                    'company'         => $company,
                    'property'        => $property->load([
                        'units:id,property_id,unit_name,unit_code,is_active'
                    ]),
                    'tenants'         => $this->tenantsForCompany($company->id),
                    'currencyOptions' => $this->currencyOptions(),
                    'intervalOptions' => $this->intervalOptions(),
                    'renewedFrom'     => null,
                    'contract'        => $contract,   // ← Pass the FULL contract (not just loaded relation)
                ]);
            }

    // ═══════════════════════════════════════════════════════════════════
    // Update
    // ═══════════════════════════════════════════════════════════════════

        public function update(Request $request, Company $company, Property $property, RentContract $contract)
        {
            $this->authorizeCompany($company);

            $data = $this->validateContract($request, $company);

            $basis = !empty($data['min_monthly_rent']) && $data['min_monthly_rent'] > 0
                        ? $data['min_monthly_rent']
                        : $data['monthly_rent_amount'];

            $insuranceAmount = $basis * (int) ($data['insurance_months'] ?? 0);

            $contract->update([
                'property_unit_id'           => $data['property_unit_id'] ?? null,
                'revenue_type'               => $data['revenue_type'],
                'management_fee_rate'        => $data['management_fee_rate'] ?? null,
                'tenant_nature'              => $data['tenant_nature'],
                'customer_id'                => $data['customer_id'],
                'start_date'                 => $data['start_date'],
                'end_date'                   => $data['end_date'],
                'contract_currency'          => $data['contract_currency'],
                'monthly_rent_amount'        => $data['monthly_rent_amount'],
                'variable_revenue_pct'       => $data['variable_revenue_pct'] ?? null,
                'min_monthly_rent'           => $data['min_monthly_rent'] ?? null,
                'collection_currency'        => $data['collection_currency'],
                'collection_interval_months' => $data['collection_interval_months'],
                'insurance_months'           => $data['insurance_months'] ?? 0,
                'insurance_amount'           => $insuranceAmount,
                'annual_increase_rate'       => $data['annual_increase_rate'] ?? 0,
                'updated_by'                 => auth()->id(),
            ]);

            // Optional: Regenerate schedules on edit (recommended)
            $contract->revenues()->delete();
            $contract->collections()->delete();
            $contract->generateSchedules();

            return redirect()
                ->route('company.properties.contracts.index', [$company->id, $property->id])
                ->with('success', 'Contract updated and schedule regenerated successfully.');
        }

    // ═══════════════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════════════
    public function show(Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);

        $contract->load([
            'customer:id,customer_name,tenant_nature,business_sector',
            'propertyUnit:id,unit_name,unit_code',
            'revenues',
            'collections',
            'renewedFrom:id,start_date,end_date',
        ]);

        return Inertia::render('Contracts/Show', [
            'company'  => $company,
            'property' => $property->load('propertyCategory:id,category_name', 'propertyType:id,type_name'),
            'contract' => $contract,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // RENEW — pre-fills create form from existing contract
    // ═══════════════════════════════════════════════════════════════════
    public function renew(Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);

        return Inertia::render('Contracts/Create', [
            'company'  => $company,
            'property' => $property->load([
                'units:id,property_id,unit_name,unit_code,is_active',
            ]),
            'tenants'         => $this->tenantsForCompany($company->id),
            'currencyOptions' => $this->currencyOptions(),
            'intervalOptions' => $this->intervalOptions(),
            'renewedFrom'     => $contract->load('customer:id,customer_name'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TERMINATE
    // ═══════════════════════════════════════════════════════════════════
    public function terminate(Request $request, Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'terminated_date'   => 'required|date',
            'termination_notes' => 'nullable|string|max:1000',
        ]);

        $contract->update([
            'status'            => RentContract::STATUS_TERMINATED,
            'terminated_date'   => $data['terminated_date'],
            'termination_notes' => $data['termination_notes'] ?? null,
        ]);

        return back()->with('success', 'Contract terminated.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // MARK COLLECTION AS COLLECTED
    // ═══════════════════════════════════════════════════════════════════
    public function markCollected(Request $request, Company $company, Property $property, RentContract $contract, RentCollection $collection)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'collected_date' => 'required|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $collection->update([
            'status'         => RentCollection::STATUS_COLLECTED,
            'collected_date' => $data['collected_date'],
            'notes'          => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Collection marked as collected.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);
        $contract->revenues()->delete();
        $contract->collections()->delete();
        $contract->delete();
        return back()->with('success', 'Contract deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════
    private function validateContract(Request $request, Company $company): array
    {
        return $request->validate([
            'property_unit_id'           => 'nullable|exists:property_units,id',
            'revenue_type'               => 'required|in:direct_rent,management_fee',
            'management_fee_rate'        => 'nullable|numeric|min:0|max:100',
            'tenant_nature'              => 'required|in:individual,corporate',
            'customer_id'                => 'required|exists:customers,id',
            'start_date'                 => 'required|date',
            'end_date'                   => 'required|date|after:start_date',
            'contract_currency'          => 'required|string|max:10',
            'monthly_rent_amount'        => 'required|numeric|min:0',
            'variable_revenue_pct'       => 'nullable|numeric|min:0|max:100',
            'min_monthly_rent'           => 'nullable|numeric|min:0',
            'collection_currency'        => 'required|string|max:10',
            'collection_interval_months' => 'required|integer|in:1,2,3,4,6,12',
            'insurance_months'           => 'nullable|integer|min:0|max:24',
            'annual_increase_rate'       => 'nullable|numeric|min:0|max:100',
            'renewed_from_contract_id'   => 'nullable|exists:rent_contracts,id',
        ]);
    }

    private function tenantsForCompany(int $companyId): array
    {
        return Customer::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('tenant_nature')
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'tenant_nature', 'business_sector'])
            ->toArray();
    }

    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'SAR', 'AED', 'QAR'];
    }

    private function intervalOptions(): array
    {
        return [
            ['value' => 1,  'label' => 'Monthly (1 Month)'],
            ['value' => 2,  'label' => '2 Months in Advance'],
            ['value' => 3,  'label' => '3 Months in Advance'],
            ['value' => 4,  'label' => '4 Months in Advance'],
            ['value' => 6,  'label' => '6 Months in Advance'],
            ['value' => 12, 'label' => '12 Months in Advance (Annual)'],
        ];
    }

    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }
}