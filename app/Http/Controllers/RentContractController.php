<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use App\Models\RentCollection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class RentContractController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // INDEX — all contracts for a property (3 tabs)
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

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
        $this->authorizeProperty($company, $property);

        return Inertia::render('Contracts/Create', [
            'company'  => $company,
            'property' => $property->load([
                'units:id,property_id,unit_name,unit_code,is_active',
            ]),
            'tenants'             => $this->tenantsForCompany($company->id),
            'currencyOptions'     => $this->currencyOptions(),
            'intervalOptions'     => $this->intervalOptions(),
            'renewedFrom'         => null,
            'baseCurrency'        => strtoupper($company->currency ?: 'EGP'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $data = $this->normalizeManagementFees($this->validateContract($request, $company));

        $this->recordFxRateFromContract($company, $data);

        // Auto-calculate insurance amount — in collection_currency, not
        // contract_currency (see computeInsurance() docblock).
        $insurance = $this->computeInsurance($company, $data);

        // Fix for audit finding F-2 — contract creation and its revenue/
        // collection schedule generation used to be two separate, untransacted
        // steps. If generateSchedules() threw partway through (bad data,
        // lock timeout, DB hiccup under concurrent use), the contract would
        // already exist with no — or a partial — financial schedule behind
        // it, and nothing would roll it back. Wrapping both steps in one
        // transaction means either the whole contract + its full schedule is
        // saved, or none of it is.
        $contract = DB::transaction(function () use ($company, $property, $data, $insurance) {
            $contract = RentContract::create([
                'company_id'                 => $company->id,
                'property_id'                => $property->id,
                'property_unit_id'           => $data['property_unit_id'] ?? null,
                'revenue_type'               => $data['revenue_type'],
                'management_fee_rate'        => $data['management_fee_rate'] ?? null,
                'has_management_fees'        => (bool) ($data['has_management_fees'] ?? false),
                'management_fee_expense_rate'=> !empty($data['has_management_fees']) ? ($data['management_fee_expense_rate'] ?? 0) : null,
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
                'insurance_amount'           => $insurance['amount'],
                'insurance_currency'         => $insurance['currency'],
                'annual_increase_rate'       => $this->legacyAnnualRateFromSchedule($data),
                'annual_increase_schedule'   => $this->normalizeIncreaseSchedule($data['annual_increase_schedule'] ?? []),
                'renewed_from_contract_id'   => $data['renewed_from_contract_id'] ?? null,
                'status'                     => 'running',
                'created_by'                 => auth()->id(),
            ]);

            // Generate rent revenues + collection schedule (itself
            // transaction-wrapped — see RentContract::generateSchedules()).
            // Nesting is safe: Laravel treats a transaction started inside
            // another as a savepoint, and any failure here bubbles up and
            // rolls back this outer transaction too, so the contract row
            // itself never survives a failed schedule generation.
            $contract->generateSchedules();

            return $contract;
        });

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
                $this->authorizeProperty($company, $property);
                $this->authorizeContract($property, $contract);

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
                    'baseCurrency'    => strtoupper($company->currency ?: 'EGP'),
                    // Confirmed product decision — a contract with any
                    // 'collected' row is locked for editing entirely (every
                    // field, since any save regenerates the whole schedule).
                    // The frontend uses this to disable the form and show an
                    // explanatory banner; update() below enforces the same
                    // rule server-side regardless of what the UI does.
                    'hasCollectedHistory' => $contract->collections()
                        ->where('status', RentCollection::STATUS_COLLECTED)
                        ->exists(),
                ]);
            }

    // ═══════════════════════════════════════════════════════════════════
    // Update
    // ═══════════════════════════════════════════════════════════════════

        public function update(Request $request, Company $company, Property $property, RentContract $contract)
        {
            $this->authorizeCompany($company);
            $this->authorizeProperty($company, $property);
            $this->authorizeContract($property, $contract);

            // Confirmed product decision — a contract with any 'collected'
            // row is locked for editing entirely, until those rows are
            // deleted first (Rent Collections tab, deleteCollection()
            // above). The frontend disables the whole form for this case,
            // but this check is the real enforcement — it rejects the
            // update even if the UI lock were somehow bypassed.
            $hasCollectedHistory = $contract->collections()
                ->where('status', RentCollection::STATUS_COLLECTED)
                ->exists();

            if ($hasCollectedHistory) {
                return back()->withErrors([
                    'contract' => 'This contract has collected rent payments on record and cannot be edited. Delete the collected rows first (Rent Collections tab) if you need to edit this contract.',
                ]);
            }

            $data = $this->normalizeManagementFees($this->validateContract($request, $company));

            $this->recordFxRateFromContract($company, $data);

            $insurance = $this->computeInsurance($company, $data);

            // Fix for audit finding F-2 — update() + generateSchedules() used
            // to run as two untransacted steps with no row lock, so (a) a
            // failure partway through could leave the contract's fields
            // updated but its schedule stale/partial, and (b) two people
            // editing the same contract at the same moment could race each
            // other. Re-fetching the contract with lockForUpdate() inside a
            // transaction serializes concurrent edits to the same contract
            // (the second request simply waits for the first to commit
            // instead of racing it), and the transaction ensures the field
            // update and the schedule regeneration succeed or fail together.
            DB::transaction(function () use ($contract, $data, $insurance) {
                $locked = RentContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();

                $locked->update([
                    'property_unit_id'           => $data['property_unit_id'] ?? null,
                    'revenue_type'               => $data['revenue_type'],
                    'management_fee_rate'        => $data['management_fee_rate'] ?? null,
                    'has_management_fees'        => (bool) ($data['has_management_fees'] ?? false),
                    'management_fee_expense_rate'=> !empty($data['has_management_fees']) ? ($data['management_fee_expense_rate'] ?? 0) : null,
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
                    'insurance_amount'           => $insurance['amount'],
                    'insurance_currency'         => $insurance['currency'],
                    'annual_increase_rate'       => $this->legacyAnnualRateFromSchedule($data),
                    'annual_increase_schedule'   => $this->normalizeIncreaseSchedule($data['annual_increase_schedule'] ?? []),
                    'updated_by'                 => auth()->id(),
                ]);

                // Regenerate the schedule. As of the C1 fix, generateSchedules()
                // no longer destroys collection history — it reconciles the new
                // schedule against existing rows, refreshing only rows still
                // 'pending' and leaving anything already 'collected' or
                // 'overdue' untouched. Do NOT manually delete
                // revenues()/collections() here — that would defeat the whole
                // point of the reconciliation.
                $locked->generateSchedules();
            });

            return redirect()
                ->route('company.properties.contracts.index', [$company->id, $property->id])
                ->with('success', 'Contract updated and schedule regenerated (existing collection history preserved).');
        }

    // ═══════════════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════════════
    public function show(Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);

        $contract->load([
            'customer:id,customer_name,tenant_nature,business_sector',
            'propertyUnit:id,unit_name,unit_code',
            'revenues',
            'collections',
            'renewedFrom:id,start_date,end_date',
            'createdBy:id,name',
            'updatedBy:id,name',
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
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);

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
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);

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
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);
        abort_unless($collection->rent_contract_id === $contract->id, 404);

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
    // UNCOLLECT — undo a Mark Collected, keeping the row intact
    // ═══════════════════════════════════════════════════════════════════
    /**
     * Confirmed product decision (July 2026 session): the Delete button on
     * a collection row was removed from the UI, because a collection is a
     * scheduled slice of the contract's revenue, not something the user
     * created by hand — deleting one outright can desync the schedule
     * from the contract. This is the real "undo" instead, mirroring
     * PropertyInstallmentDue's markUnpaid() exactly: the row survives,
     * only its status and collected_date are reverted. Status reverts to
     * 'overdue' if collection_date has already passed, 'pending'
     * otherwise — the same state a freshly-generated row in that
     * situation would be in, rather than silently going stale.
     */
    public function markUncollected(Company $company, Property $property, RentContract $contract, RentCollection $collection)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);
        abort_unless($collection->rent_contract_id === $contract->id, 404);

        if ($collection->status !== RentCollection::STATUS_COLLECTED) {
            return back()->withErrors(['collection' => 'This collection is not currently marked as collected.']);
        }

        $newStatus = Carbon::parse($collection->collection_date)->lt(Carbon::today())
            ? RentCollection::STATUS_OVERDUE
            : RentCollection::STATUS_PENDING;

        $collection->update([
            'status'         => $newStatus,
            'collected_date' => null,
        ]);

        return back()->with('success', 'Collection reverted to ' . $newStatus . '.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DELETE SINGLE COLLECTION — explicit, deliberate row deletion
    // ═══════════════════════════════════════════════════════════════════
    /**
     * Deletes exactly one collection row, at any status — including
     * 'collected'. This is intentional: deleting a collected row is exactly
     * the escape valve meant to unblock editing/deleting a contract that
     * has real collection history (see the lock added to update() and
     * destroy() below). Unlike the installment-due equivalent, there's no
     * "protect paid rows from deletion" rule here at all — for VERO's use
     * case (property management, not accounting), the user needs to be able
     * to remove a genuinely wrong collected row, and the frontend is
     * responsible for a stronger confirmation before calling this when the
     * row is 'collected'.
     */
    public function deleteCollection(Company $company, Property $property, RentContract $contract, RentCollection $collection)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);
        abort_unless($collection->rent_contract_id === $contract->id, 404);

        $collection->delete();

        return back()->with('success', 'Collection removed.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property, RentContract $contract)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeContract($property, $contract);

        // Fix for audit finding H-1 — this used to unconditionally delete
        // every revenue and collection row for the contract, including any
        // already marked 'collected' (real cash the tenant has actually
        // paid, with its own collected_date/notes). Nothing else in this
        // app ever destroys that kind of history on an edit (see the C1/C2
        // reconciliation fixes on RentContract::reconcileCollections() and
        // PropertyInstallmentPlan::generateDues()) — deletion shouldn't be
        // the one place that still can.
        //
        // Confirmed product decision: a contract with any 'collected' row
        // cannot be edited or deleted until those rows are removed first,
        // one at a time, via deleteCollection() above (Rent Collections
        // tab). Termination is a separate action — it ends the lease with
        // the tenant — and is deliberately NOT offered here as a substitute
        // for deletion; the message below no longer mentions it.
        $hasCollectedHistory = $contract->collections()
            ->where('status', RentCollection::STATUS_COLLECTED)
            ->exists();

        if ($hasCollectedHistory) {
            return back()->withErrors([
                'contract' => 'This contract has collected rent payments on record and cannot be deleted. Delete the collected rows first (Rent Collections tab) if you need to remove this contract entirely.',
            ]);
        }

        // Fix for audit finding F-2 — these three deletes used to run
        // untransacted; a failure between them (e.g. the contract row
        // itself failing to delete due to an unexpected FK from another
        // table) could leave revenues/collections gone but the contract
        // still present, or vice versa.
        DB::transaction(function () use ($contract) {
            $contract->revenues()->delete();
            $contract->collections()->delete();
            $contract->delete();
        });

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
            // Management Fee Revenue (this contract's revenue_type) and Management Fees
            // Expense are mutually exclusive: a unit owned by someone else and managed by
            // us for a fee revenue can never also carry a management fee expense — that
            // expense only applies when we own the unit and pay someone else to manage it.
            // Note: `has_management_fees` is intentionally NOT validated with prohibited_if
            // here — Laravel's "prohibited" family treats an explicit `false` as a present,
            // non-empty value, which would reject perfectly normal submissions where the
            // frontend correctly resets the checkbox to false. The exclusivity is instead
            // force-enforced in code below (see normalizeManagementFees()).
            'has_management_fees'        => 'nullable|boolean',
            'management_fee_expense_rate'=> 'nullable|numeric|min:0|max:100|prohibited_if:revenue_type,management_fee',
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
            // FX rate at signing — only meaningful when contract_currency differs from
            // the company's base currency. This is NOT stored on the contract; it's a
            // convenience that seeds the company's Exchange Rates table (see
            // recordFxRateFromContract() below), the same single source of truth the
            // Dashboard/Cash Forecast/Reports read from. Updating the rate later (via
            // the Exchange Rates page or a fresh Statistica pull) is what actually moves
            // future reporting — this field just avoids re-typing it during contract entry.
            'fx_rate'                    => 'nullable|numeric|min:0.000001',
            'insurance_months'           => 'nullable|integer|min:0|max:24',
            'annual_increase_schedule'   => 'nullable|array',
            'annual_increase_schedule.*.year' => 'required|integer|min:1900|max:3000',
            'annual_increase_schedule.*.rate' => 'required|numeric|min:0|max:100',
            'renewed_from_contract_id'   => 'nullable|exists:rent_contracts,id',
        ]);
    }

    /**
     * Management Fee Revenue (revenue_type = management_fee — unit owned by someone
     * else, we earn a % fee) and Management Fees Expense (has_management_fees — we own
     * the unit and pay someone else to manage it) are mutually exclusive business
     * scenarios. Regardless of what the client submitted, force the expense side off
     * whenever this is a management-fee-revenue contract, so the two can never both
     * apply to the same contract (which would otherwise double-deduct a fee on top of
     * a fee in the Cash Forecast).
     */
    private function normalizeManagementFees(array $data): array
    {
        if (($data['revenue_type'] ?? null) === 'management_fee') {
            $data['has_management_fees']         = false;
            $data['management_fee_expense_rate'] = null;
        }

        return $data;
    }

    /**
     * If the user typed an FX rate on the contract form, save it into the
     * company's Exchange Rates table (currency_rates) — NOT onto the
     * contract. This is a convenience shortcut for data entry only; the
     * actual source of truth stays the single Exchange Rates table, so a
     * later update there (or a Statistica pull) is what moves reporting,
     * exactly as it would if the user had entered the rate directly on the
     * Exchange Rates page instead of here.
     *
     * Dated to the contract's start_date — "the rate as of signing" — which
     * naturally gets superseded the moment a later-dated rate is entered
     * anywhere else for the same currency, since every FX lookup in this
     * app always uses the latest rate on file, not this specific one.
     */
    private function recordFxRateFromContract(Company $company, array $data): void
    {
        $rate     = $data['fx_rate'] ?? null;
        $currency = strtoupper($data['contract_currency'] ?? '');
        $base     = strtoupper($company->currency ?: 'EGP');

        if (!$rate || $rate <= 0 || $currency === '' || $currency === $base) {
            return;
        }

        \App\Models\CurrencyRate::updateOrCreate(
            ['company_id' => $company->id, 'currency' => $currency, 'rate_date' => $data['start_date']],
            ['rate' => $rate, 'source' => \App\Models\CurrencyRate::SOURCE_CONTRACT_ENTRY, 'created_by' => auth()->id()]
        );
    }

    /**
     * Insurance is real cash collected from the tenant alongside the rent —
     * it should be denominated in collection_currency, not contract_currency
     * (which can differ, e.g. rent negotiated in USD but actually collected
     * in EGP). Converts the rent basis into collection_currency using
     * whatever FX rate is available at the moment the contract is saved
     * (the contract's own just-entered rate takes priority for its own
     * currency; otherwise the latest rate on file for each leg).
     *
     * Like monthly_rent_amount and every other snapshot field on the
     * contract, this is computed ONCE at save time — it is not continuously
     * re-priced afterward. Only the Dashboard/Cash Forecast/Reports
     * "main functional currency" views always use the latest rate live;
     * a stored deposit amount on a contract is a recorded fact, not a
     * live-updating report figure.
     */
    private function computeInsurance(Company $company, array $data): array
    {
        $basis = !empty($data['min_monthly_rent']) && $data['min_monthly_rent'] > 0
            ? (float) $data['min_monthly_rent']
            : (float) $data['monthly_rent_amount'];

        $months        = (int) ($data['insurance_months'] ?? 0);
        $rawAmount     = $basis * $months;
        $contractCcy   = strtoupper($data['contract_currency']);
        $collectionCcy = strtoupper($data['collection_currency']);

        if ($contractCcy === $collectionCcy) {
            return ['amount' => round($rawAmount, 2), 'currency' => $collectionCcy];
        }

        $fx = app(\App\Services\CurrencyConversionService::class);
        $baseCurrency = strtoupper($company->currency ?: 'EGP');

        // Prefer the rate the user just entered on this form for the contract's
        // own currency leg, so insurance reflects the same rate they just typed
        // in rather than possibly-stale data already on file.
        $manualRate = !empty($data['fx_rate']) ? (float) $data['fx_rate'] : null;

        if ($manualRate && $contractCcy !== $baseCurrency) {
            $inBase = $rawAmount * $manualRate;
            if ($collectionCcy === $baseCurrency) {
                return ['amount' => round($inBase, 2), 'currency' => $collectionCcy];
            }
            $rate2 = $fx->latestRate($company->id, $collectionCcy);
            if ($rate2 !== null) {
                return ['amount' => round($inBase / $rate2, 2), 'currency' => $collectionCcy];
            }
        }

        $converted = $fx->convertBetween($company->id, $baseCurrency, $rawAmount, $contractCcy, $collectionCcy);

        if ($converted !== null) {
            return ['amount' => $converted['amount'], 'currency' => $collectionCcy];
        }

        // No rate available at all for this pair yet — fall back to the raw
        // amount labeled in the contract currency rather than silently
        // producing a wrong number in the wrong currency, and let the
        // caller/UI know conversion is still needed.
        return ['amount' => round($rawAmount, 2), 'currency' => $contractCcy, 'needs_fx_rate' => true];
    }

    private function normalizeIncreaseSchedule(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($r) => isset($r['year']) && isset($r['rate']))
            ->map(fn ($r) => [
                'year' => (int) $r['year'],
                'rate' => round((float) $r['rate'], 2),
            ])
            ->sortBy('year')
            ->values()
            ->all();
    }

    private function legacyAnnualRateFromSchedule(array $data): float
    {
        $schedule = $this->normalizeIncreaseSchedule($data['annual_increase_schedule'] ?? []);
        if (empty($schedule)) {
            return 0;
        }

        return (float) $schedule[0]['rate'];
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

    /**
     * Fix for audit finding C-2 — authorizeCompany() only confirms the
     * logged-in user belongs to {company}. It never confirmed {property}
     * (bound by Laravel with no company filter) actually belongs to that
     * same company, letting a user reach another company's property/
     * contract/collection rows by supplying their own company ID plus a
     * foreign resource ID. 404 rather than 403 so this doesn't confirm to
     * an attacker that the ID exists at all.
     */
    private function authorizeProperty(Company $company, Property $property): void
    {
        abort_unless($property->company_id === $company->id, 404);
    }

    private function authorizeContract(Property $property, RentContract $contract): void
    {
        abort_unless($contract->property_id === $property->id, 404);
    }
}