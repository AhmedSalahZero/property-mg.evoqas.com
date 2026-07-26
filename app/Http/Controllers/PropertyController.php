<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\PropertyMarketValue;
use App\Models\PropertyCategory;
use App\Models\Province;
use App\Models\PropertyOwner;
use App\Models\Tag;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyController extends Controller
{
    use AuthorizesCompany;

    /**
     * Fix for audit Findings 1 / 3 / 4 (July 2026 cross-audit) — computes
     * the base-currency conversion for a property/unit's acquisition_cost
     * and book_value, both stored once at write time using the FX rate in
     * effect on acquisition_date (the same "transaction currency +
     * functional currency, frozen at the record's own date" pattern
     * already used for rent_revenues/rent_collections/property_expenses/
     * property_expense_payments/property_installment_dues — see
     * CurrencyConversionService::convert()). Both figures share one FX
     * rate since they're always denominated in the same `currency` field.
     *
     * acquisition_date is stored as "MM/YYYY"; falls back to today if
     * blank so a property saved with no acquisition date still gets a
     * best-effort conversion at today's rate rather than none at all.
     */
    private function propertyValuationConversion(int $companyId, string $companyCurrency, ?string $acquisitionCost, ?string $bookValue, ?string $currency, ?string $acquisitionDate): array
    {
        $currency = $currency ?: 'EGP';
        $date     = $this->parseMonthYearOrToday($acquisitionDate);
        $fx       = app(CurrencyConversionService::class);

        $acqConversion  = $fx->convert($companyId, $companyCurrency, (float) ($acquisitionCost ?? 0), $currency, $date);
        $bookConversion = $fx->convert($companyId, $companyCurrency, (float) ($bookValue ?? 0), $currency, $date);

        return [
            'acquisition_cost_base_amount' => $acquisitionCost !== null && $acquisitionCost !== '' ? $acqConversion['base_amount'] : null,
            'book_value_base_amount'       => $bookValue !== null && $bookValue !== '' ? $bookConversion['base_amount'] : null,
            'base_currency'                => $acqConversion['base_currency'],
            // Both legs use the same rate (same currency, same date) — the
            // acquisition leg's rate is used as the row's single fx_rate_used.
            'fx_rate_used'                 => $acqConversion['fx_rate_used'],
        ];
    }

    /**
     * Same conversion, for a single market_value entry — uses the rate in
     * effect on that specific value_date rather than acquisition_date,
     * since each market value repeater row is its own dated valuation event.
     */
    private function marketValueConversion(int $companyId, string $companyCurrency, float $marketValue, ?string $currency, ?string $valueDate): array
    {
        $currency = $currency ?: 'EGP';
        $date     = $this->parseMonthYearOrToday($valueDate);
        $fx       = app(CurrencyConversionService::class);

        return $fx->convert($companyId, $companyCurrency, $marketValue, $currency, $date);
    }

    /**
     * Fix (July 2026) — acquisition_date/value_date are entered via a plain
     * <input type="month">, which the HTML spec always outputs/round-trips
     * as "YYYY-MM" (e.g. "2025-03"), not "MM/YYYY". That's the actual format
     * sitting in the database — confirmed against acquisition_date rows —
     * even though earlier comments in this file assumed "MM/YYYY". Passing
     * "2025-03" to Carbon::createFromFormat('m/Y', ...) always threw and
     * silently fell back to today(), which meant: (1) acquisition_cost/
     * book_value were being FX-converted at today's rate instead of the
     * rate on the real acquisition date, and (2) auto-generated property
     * codes carried today's YY/MM instead of the acquisition date's.
     * Now accepts both "YYYY-MM" and "MM/YYYY", same dual-format handling
     * InstallmentScheduleGenerator::parseMonthYear() already uses for
     * delivery_date.
     */
    private function parseMonthYearOrToday(?string $value): Carbon
    {
        if (empty($value)) {
            return Carbon::today();
        }
        $value = trim($value);
        try {
            if (preg_match('#^\d{4}-\d{1,2}$#', $value)) {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            }
            return Carbon::createFromFormat('m/Y', $value)->startOfMonth();
        } catch (\Exception $e) {
            return Carbon::today();
        }
    }

    /**
     * Building/Land/Complex parent records have no acquisition_date of
     * their own — only their child units do. Used to generate the
     * parent's own auto-code (see generatePropertyCode()) from the
     * EARLIEST acquisition_date among the child units submitted in the
     * same request, rather than always falling back to today's date.
     * Returns the raw submitted string (still "YYYY-MM" or "MM/YYYY") for
     * parseMonthYearOrToday() to parse again — not a re-formatted date —
     * so both call sites stay in perfect agreement on what counts as valid.
     */
    private function earliestAcquisitionDate(array $units): ?string
    {
        $earliestRaw = null;
        $earliestParsed = null;

        foreach ($units as $u) {
            $raw = $u['acquisition_date'] ?? null;
            if (empty($raw)) continue;

            $parsed = $this->parseMonthYearOrToday($raw);
            if ($earliestParsed === null || $parsed->lt($earliestParsed)) {
                $earliestParsed = $parsed;
                $earliestRaw = $raw;
            }
        }

        return $earliestRaw;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROPERTY CODE AUTO-GENERATION (confirmed format, July 2026)
    //   {3-letter company prefix}-{type prefix}-{YYMM}-{4-digit sequence}
    //   e.g. VER-UNT-2607-0001
    // Sequence resets per company + type + acquisition year/month, since
    // that combination is already baked into the prefix — it exists only
    // to disambiguate two properties of the same type acquired the same
    // month. Falls back to today's year/month if no acquisition date was
    // given yet (e.g. Usufruct / Managed For Others properties).
    // ═══════════════════════════════════════════════════════════════════
    private function generatePropertyCode(Company $company, string $nature, ?string $acquisitionDate): string
    {
        $companyPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company->name ?? ''), 0, 3));
        $companyPrefix = str_pad($companyPrefix, 3, 'X');

        $typePrefixes = [
            'unit'     => 'UNT',
            'building' => 'BLD',
            'land'     => 'LND',
            'complex'  => 'CPX',
        ];
        $typePrefix = $typePrefixes[$nature] ?? 'PRO';

        $date  = $this->parseMonthYearOrToday($acquisitionDate);
        $yymm  = $date->format('ym');

        $prefixBase = "{$companyPrefix}-{$typePrefix}-{$yymm}-";

        $nextSeq = $this->nextSequenceForPrefix(
            Property::where('company_id', $company->id),
            'property_code',
            $prefixBase
        );

        return $prefixBase . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CHILD UNIT CODE AUTO-GENERATION (confirmed format, July 2026)
    //   {Parent Property Code}-UNIT-{4-digit sequence}
    // Sequence is continuous across the whole company — never resets per
    // parent — so it stays globally unique company-wide even though the
    // parent's code is prefixed on for traceability.
    // ═══════════════════════════════════════════════════════════════════
    private function generateUnitCode(Company $company, string $parentPropertyCode): string
    {
        $nextSeq = $this->nextSequenceForPrefix(
            PropertyUnit::where('company_id', $company->id),
            'unit_code',
            null,
            '-UNIT-'
        );

        return "{$parentPropertyCode}-UNIT-" . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    // Shared helper — finds the highest existing 4-digit sequence for a
    // given code pattern and returns the next one. Looks at the max
    // existing number rather than a simple row count, so a deleted or
    // manually-entered code never causes a collision.
    //   - $prefix: exact leading string to match (property codes)
    //   - $marker: a substring to locate before the trailing digits
    //     (unit codes, where the leading part — the parent code — varies)
    private function nextSequenceForPrefix($query, string $column, ?string $prefix, ?string $marker = null): int
    {
        if ($prefix !== null) {
            $codes = (clone $query)->where($column, 'like', $prefix . '%')->pluck($column);
            $needleLen = strlen($prefix);
        } else {
            $codes = (clone $query)->where($column, 'like', '%' . $marker . '%')->pluck($column);
            $needleLen = null;
        }

        $max = 0;
        foreach ($codes as $code) {
            if ($marker !== null) {
                $pos = strrpos($code, $marker);
                if ($pos === false) continue;
                $suffix = substr($code, $pos + strlen($marker));
            } else {
                $suffix = substr($code, $needleLen);
            }
            if (preg_match('/^(\d+)/', $suffix, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }

    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $properties = Property::forCompany($company->id)
            ->with([
                'propertyCategory:id,category_name',
                'propertyType:id,type_name',
                // Installment plan — only delivery_date needed for status
                'installmentPlan:id,property_id,delivery_date',
                // Running contracts on the property itself (standalone unit)
                'rentContracts' => fn($q) => $q->where('status', 'running')
                                               ->select('id','property_id','property_unit_id','status'),
                // Sale record, if this standalone unit has been sold
                'sales' => fn($q) => $q->select('id','property_id','property_unit_id','sale_date','buyer_name','sale_price','currency','net_sale_proceeds','realized_gain_loss','base_currency','payment_method')
                    ->with(['dues:id,property_sale_id,due_type,due_date,amount,currency,status']),
                'units' => fn($q) => $q->with([
                    'propertyCategory:id,category_name',
                    'propertyType:id,type_name',
                    'marketValues' => fn($q) => $q->orderByDesc('value_date')->limit(1),
                    // Running contracts on child units
                    'rentContracts' => fn($q) => $q->where('status', 'running')
                                                   ->select('id','property_id','property_unit_id','status'),
                    // Sale record, if this child unit has been sold
                    'sales' => fn($q) => $q->select('id','property_id','property_unit_id','sale_date','buyer_name','sale_price','currency','net_sale_proceeds','realized_gain_loss','base_currency','payment_method')
                        ->with(['dues:id,property_sale_id,due_type,due_date,amount,currency,status']),
                ]),
                'marketValues' => fn($q) => $q->orderByDesc('value_date')->limit(1),
                'tags:id,name',
            ])
            ->orderBy('nature')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Properties/Index', [
            'company'    => $company,
            'properties' => $properties,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════════
    public function create(Company $company)
    {
        $this->authorizeCompany($company);

        $categories = PropertyCategory::where('company_id', $company->id)
            ->with(['types' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Properties/Create', [
            'company'    => $company,
            'categories' => $categories,
            'ownershipOptions' => $this->ownershipOptions(),
            'governorates'     => $this->egyptianGovernorates(),
            'uomOptions'       => $this->uomOptions(),
            'currencyOptions'  => $this->currencyOptions(),
            'provinces'        => Province::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'propertyOwners'   => PropertyOwner::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $nature = $request->input('nature');

        // ── Base validation ───────────────────────────────────────────
        $base = $request->validate([
            'nature'        => 'required|in:unit,building,land,complex',
            'property_name' => 'required|string|max:255',
            'property_code' => 'nullable|string|max:100',
            'ownership'     => 'required|in:fully_owned,installments,usufruct,managed',
            // Only meaningful for Usufruct / Managed For Others — see
            // ownerNameErrors() for the conditional-required check.
            'owner_name'    => 'nullable|string|max:255',
            'country'       => 'nullable|string|max:100',
            'governorate'   => 'nullable|string|max:150',
            'province'      => 'nullable|string|max:150',
            'location'      => 'nullable|string|max:500',
            'description_tag_ids'   => 'nullable|array',
            'description_tag_ids.*' => 'integer',
        ]);

        // ── Unit-specific financial validation ────────────────────────
        if ($nature === 'unit') {
            $request->validate([
                'property_category_id'         => 'nullable|exists:property_categories,id',
                'property_type_id'             => 'nullable|exists:property_types,id',
                'area'                         => 'nullable|numeric|min:0',
                'unit_of_measurement'          => 'nullable|string|max:50',
                'acquisition_cost'             => 'nullable|numeric|min:0',
                'currency'                     => 'nullable|string|max:10',
                'acquisition_date'             => 'nullable|string|max:7',
                'book_value'                   => 'nullable|numeric|min:0',
                'accumulated_depreciation'     => 'nullable|numeric|min:0',
                'monthly_depreciation'         => 'nullable|numeric|min:0',
                'depreciation_duration_months' => 'nullable|integer|min:0',
                // market values repeater
                'market_values'                => 'nullable|array',
                'market_values.*.market_value' => 'required|numeric|min:0',
                'market_values.*.value_date'   => 'required|string|max:7',
            ]);
        }

        // ── Units repeater validation (building / land / complex) ─────
        if (in_array($nature, ['building', 'land', 'complex'])) {
            $request->validate([
                'units'                                  => 'nullable|array',
                'units.*.unit_name'                      => 'required|string|max:255',
                'units.*.slot_type'                      => 'required|in:built_unit,land_slot',
                'units.*.unit_code'                      => 'nullable|string|max:100',
                'units.*.ownership'                      => 'nullable|in:fully_owned,installments,usufruct,managed',
                'units.*.owner_name'                      => 'nullable|string|max:255',
                'units.*.property_category_id'           => 'nullable|exists:property_categories,id',
                'units.*.property_type_id'               => 'nullable|exists:property_types,id',
                'units.*.area'                           => 'nullable|numeric|min:0',
                'units.*.unit_of_measurement'            => 'nullable|string|max:50',
                'units.*.acquisition_cost'               => 'nullable|numeric|min:0',
                'units.*.currency'                       => 'nullable|string|max:10',
                'units.*.acquisition_date'               => 'nullable|string|max:7',
                'units.*.book_value'                     => 'nullable|numeric|min:0',
                'units.*.accumulated_depreciation'       => 'nullable|numeric|min:0',
                'units.*.monthly_depreciation'           => 'nullable|numeric|min:0',
                'units.*.depreciation_duration_months'   => 'nullable|integer|min:0',
                'units.*.market_values'                  => 'nullable|array',
                'units.*.market_values.*.market_value'   => 'required|numeric|min:0',
                'units.*.market_values.*.value_date'     => 'required|string|max:7',
            ]);
        }

        // ── Ownership-conditional asset field requirements ─────────────
        // Fix — Fully Owned / Owned with Installments must supply
        // Acquisition Date, Acquisition Cost, and a Depreciation Duration
        // of at least 1 month (unless the unit is a land slot, which
        // never depreciates). See assetFieldErrors() for why this can't
        // be expressed as a declarative required_if rule above.
        $assetErrors = array_merge(
            $this->assetFieldErrors($base['ownership'] ?? null, $nature, $request->input('units', [])),
            $this->ownerNameErrors($base['ownership'] ?? null, $nature, $request->input('units', []))
        );
        if (!empty($assetErrors)) {
            return back()->withErrors($assetErrors)->withInput();
        }

        // ── Auto-generate code if blank ───────────────────────────────
        // Confirmed format (July 2026): {company prefix}-{type}-{YYMM}-{seq}
        // e.g. VER-UNT-2607-0001 — see generatePropertyCode().
        //
        // Building/Land/Complex have no acquisition_date of their own —
        // only their child units do — so the parent's own code used to
        // always fall back to today's date (creation date) instead of any
        // real acquisition date. Fixed: uses the EARLIEST child unit's
        // acquisition_date submitted in this same request, if any.
        $code = $base['property_code'] ?? null;
        if (empty($code)) {
            $acquisitionDateForCode = $nature === 'unit'
                ? $request->input('acquisition_date')
                : $this->earliestAcquisitionDate($request->input('units', []));
            $code = $this->generatePropertyCode($company, $nature, $acquisitionDateForCode);
        }

        // ── Ensure code uniqueness per company ────────────────────────
        $exists = Property::where('company_id', $company->id)
            ->where('property_code', $code)
            ->exists();
        if ($exists) {
            return back()->withErrors(['property_code' => 'This property code is already used.'])->withInput();
        }

        // ── Create parent property ────────────────────────────────────
        $propertyData = [
            'company_id'    => $company->id,
            'nature'        => $nature,
            'property_name' => $base['property_name'],
            'property_code' => $code,
            'ownership'     => $base['ownership'],
            // Only meaningful (and kept) for Usufruct / Managed For Others —
            // see ownershipHidesAssetFields(); nulled out otherwise.
            'owner_name'    => $this->ownershipHidesAssetFields($base['ownership'] ?? null) ? ($base['owner_name'] ?? null) : null,
            'country'       => $base['country'] ?? 'Egypt',
            'governorate'   => $base['governorate'] ?? null,
            'province'      => $base['province'] ?? null,
            'location'      => $base['location'] ?? null,
            'is_active'     => true,
            'sort_order'    => Property::where('company_id', $company->id)->max('sort_order') + 1,
        ];

        // For unit nature, add financials to parent
        if ($nature === 'unit') {
            $propertyData = array_merge($propertyData, $this->nullAssetFieldsIfHidden([
                'property_category_id'         => $request->input('property_category_id'),
                'property_type_id'             => $request->input('property_type_id'),
                'area'                         => $request->input('area'),
                'unit_of_measurement'          => $request->input('unit_of_measurement'),
                'acquisition_cost'             => $request->input('acquisition_cost'),
                'currency'                     => $request->input('currency', 'EGP'),
                'acquisition_date'             => $request->input('acquisition_date'),
                'book_value'                   => $request->input('book_value'),
                'accumulated_depreciation'     => $request->input('accumulated_depreciation'),
                'monthly_depreciation'         => $request->input('monthly_depreciation'),
                'depreciation_duration_months' => $request->input('depreciation_duration_months'),
            ], $base['ownership'] ?? null));

            // Fix for audit Findings 1/3/4 — store the base-currency
            // conversion once, at write time, same as every other
            // money-bearing table in the app.
            $propertyData = array_merge($propertyData, $this->propertyValuationConversion(
                $company->id,
                $company->currency ?: 'EGP',
                $propertyData['acquisition_cost'] ?? null,
                $propertyData['book_value'] ?? null,
                $propertyData['currency'] ?? null,
                $propertyData['acquisition_date'] ?? null
            ));
        }

        $property = Property::create($propertyData);

        $this->syncPropertyTags($company, $property, $request->input('description_tag_ids', []));

        // ── Market values for standalone unit ────────────────────────
        // Usufruct/Managed For Others units aren't company assets, so a
        // market valuation doesn't apply either — skip regardless of what
        // was submitted, same rule as nullAssetFieldsIfHidden() above.
        if ($nature === 'unit' && !$this->ownershipHidesAssetFields($base['ownership'] ?? null)) {
            foreach ($request->input('market_values', []) as $mv) {
                $mvConversion = $this->marketValueConversion(
                    $company->id,
                    $company->currency ?: 'EGP',
                    (float) $mv['market_value'],
                    $propertyData['currency'] ?? 'EGP',
                    $mv['value_date']
                );
                PropertyMarketValue::create([
                    'company_id'    => $company->id,
                    'property_id'   => $property->id,
                    'market_value'  => $mv['market_value'],
                    'value_date'    => $mv['value_date'],
                    'notes'         => $mv['notes'] ?? null,
                    'base_amount'   => $mvConversion['base_amount'],
                    'base_currency' => $mvConversion['base_currency'],
                    'fx_rate_used'  => $mvConversion['fx_rate_used'],
                ]);
            }
        }

        // ── Child units for building / land / complex ─────────────────
        if (in_array($nature, ['building', 'land', 'complex'])) {
            foreach ($request->input('units', []) as $i => $u) {
                $unitOwnership = !empty($u['ownership']) ? $u['ownership'] : ($base['ownership'] ?? null);

                $unitCode = $u['unit_code'] ?? null;
                if (empty($unitCode)) {
                    $unitCode = $this->generateUnitCode($company, $code);
                }

                $unitData = $this->nullAssetFieldsIfHidden([
                    'company_id'                   => $company->id,
                    'property_id'                  => $property->id,
                    'slot_type'                    => $u['slot_type'],
                    'unit_name'                    => $u['unit_name'],
                    'unit_code'                    => $unitCode,
                    'ownership'                    => $u['ownership'] ?? null,
                    'owner_name'                   => $this->ownershipHidesAssetFields($unitOwnership) ? ($u['owner_name'] ?? null) : null,
                    'location'                     => $u['location'] ?? null,
                    'property_category_id'         => $u['property_category_id'] ?? null,
                    'property_type_id'             => $u['property_type_id'] ?? null,
                    'area'                         => $u['area'] ?? null,
                    'unit_of_measurement'          => $u['unit_of_measurement'] ?? null,
                    'acquisition_cost'             => $u['acquisition_cost'] ?? null,
                    'currency'                     => $u['currency'] ?? 'EGP',
                    'acquisition_date'             => $u['acquisition_date'] ?? null,
                    'book_value'                   => $u['book_value'] ?? null,
                    'accumulated_depreciation'     => $u['slot_type'] === 'land_slot' ? null : ($u['accumulated_depreciation'] ?? null),
                    'monthly_depreciation'         => $u['slot_type'] === 'land_slot' ? null : ($u['monthly_depreciation'] ?? null),
                    'depreciation_duration_months' => $u['slot_type'] === 'land_slot' ? null : ($u['depreciation_duration_months'] ?? null),
                    'is_active'                    => true,
                    'sort_order'                   => $i,
                ], $unitOwnership);

                // Fix for audit Findings 1/3/4 — same base-currency
                // conversion as the standalone-unit path above.
                $unitData = array_merge($unitData, $this->propertyValuationConversion(
                    $company->id,
                    $company->currency ?: 'EGP',
                    $unitData['acquisition_cost'] ?? null,
                    $unitData['book_value'] ?? null,
                    $unitData['currency'] ?? null,
                    $unitData['acquisition_date'] ?? null
                ));

                $unit = PropertyUnit::create($unitData);

                // Usufruct/Managed For Others units aren't company assets —
                // skip market valuation entries regardless of what was submitted.
                if (!$this->ownershipHidesAssetFields($unitOwnership)) {
                    foreach ($u['market_values'] ?? [] as $mv) {
                        $mvConversion = $this->marketValueConversion(
                            $company->id,
                            $company->currency ?: 'EGP',
                            (float) $mv['market_value'],
                            $unitData['currency'] ?? 'EGP',
                            $mv['value_date']
                        );
                        PropertyMarketValue::create([
                            'company_id'       => $company->id,
                            'property_unit_id' => $unit->id,
                            'market_value'     => $mv['market_value'],
                            'value_date'       => $mv['value_date'],
                            'notes'            => $mv['notes'] ?? null,
                            'base_amount'      => $mvConversion['base_amount'],
                            'base_currency'    => $mvConversion['base_currency'],
                            'fx_rate_used'     => $mvConversion['fx_rate_used'],
                        ]);
                    }
                }
            }
        }

        return redirect()
            ->route('company.properties.index', $company->id)
            ->with('success', 'Property "' . $property->property_name . '" created successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════════════
    public function show(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $property->load([
            'propertyCategory',
            'propertyType',
            'marketValues',
            'units.propertyCategory',
            'units.propertyType',
            'units.marketValues',
        ]);

        return Inertia::render('Properties/Show', [
            'company'  => $company,
            'property' => $property,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EDIT
    // ═══════════════════════════════════════════════════════════════════
    public function edit(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $property->load([
            'propertyCategory',
            'propertyType',
            'marketValues' => fn($q) => $q->orderBy('value_date'),
            'tags:id,name',
            'units' => fn($q) => $q->with([
                'propertyCategory',
                'propertyType',
                'marketValues' => fn($q) => $q->orderBy('value_date'),
            ])->orderBy('sort_order'),
        ]);

        $categories = PropertyCategory::where('company_id', $company->id)
            ->with(['types' => fn($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Properties/Edit', [
            'company'          => $company,
            'property'         => $property,
            'categories'       => $categories,
            'ownershipOptions' => $this->ownershipOptions(),
            'governorates'     => $this->egyptianGovernorates(),
            'uomOptions'       => $this->uomOptions(),
            'currencyOptions'  => $this->currencyOptions(),
            'provinces'        => Province::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'propertyOwners'   => PropertyOwner::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        $nature = $property->nature; // nature is immutable after creation

        // ── Base validation ───────────────────────────────────────────
        $base = $request->validate([
            'property_name' => 'required|string|max:255',
            'property_code' => 'nullable|string|max:100',
            'ownership'     => 'required|in:fully_owned,installments,usufruct,managed',
            'owner_name'    => 'nullable|string|max:255',
            'country'       => 'nullable|string|max:100',
            'governorate'   => 'nullable|string|max:150',
            'province'      => 'nullable|string|max:150',
            'location'      => 'nullable|string|max:500',
            'description_tag_ids'   => 'nullable|array',
            'description_tag_ids.*' => 'integer',
        ]);

        // ── Unit-specific financial validation ────────────────────────
        if ($nature === 'unit') {
            $request->validate([
                'property_category_id'         => 'nullable|exists:property_categories,id',
                'property_type_id'             => 'nullable|exists:property_types,id',
                'area'                         => 'nullable|numeric|min:0',
                'unit_of_measurement'          => 'nullable|string|max:50',
                'acquisition_cost'             => 'nullable|numeric|min:0',
                'currency'                     => 'nullable|string|max:10',
                'acquisition_date'             => 'nullable|string|max:7',
                'book_value'                   => 'nullable|numeric|min:0',
                'accumulated_depreciation'     => 'nullable|numeric|min:0',
                'monthly_depreciation'         => 'nullable|numeric|min:0',
                'depreciation_duration_months' => 'nullable|integer|min:0',
                'market_values'                => 'nullable|array',
                'market_values.*.market_value' => 'required|numeric|min:0',
                'market_values.*.value_date'   => 'required|string|max:7',
            ]);
        }

        // ── Units repeater validation (building / land / complex) ─────
        // Fix — moved up from after the parent property save (further
        // down this method used to update the parent, delete/replace its
        // market values, THEN validate the units array — so a validation
        // failure on the units block left a half-saved property). Now
        // validated up front, before any write happens.
        if (in_array($nature, ['building', 'land', 'complex'])) {
            $request->validate([
                'units'                                  => 'nullable|array',
                'units.*.id'                             => 'nullable|integer',
                'units.*.unit_name'                      => 'required|string|max:255',
                'units.*.slot_type'                      => 'required|in:built_unit,land_slot',
                'units.*.unit_code'                      => 'nullable|string|max:100',
                'units.*.ownership'                      => 'nullable|in:fully_owned,installments,usufruct,managed',
                'units.*.owner_name'                      => 'nullable|string|max:255',
                'units.*.property_category_id'           => 'nullable|exists:property_categories,id',
                'units.*.property_type_id'               => 'nullable|exists:property_types,id',
                'units.*.area'                           => 'nullable|numeric|min:0',
                'units.*.unit_of_measurement'            => 'nullable|string|max:50',
                'units.*.acquisition_cost'               => 'nullable|numeric|min:0',
                'units.*.currency'                       => 'nullable|string|max:10',
                'units.*.acquisition_date'               => 'nullable|string|max:7',
                'units.*.book_value'                     => 'nullable|numeric|min:0',
                'units.*.accumulated_depreciation'       => 'nullable|numeric|min:0',
                'units.*.monthly_depreciation'           => 'nullable|numeric|min:0',
                'units.*.depreciation_duration_months'   => 'nullable|integer|min:0',
                'units.*.market_values'                  => 'nullable|array',
                'units.*.market_values.*.market_value'   => 'required|numeric|min:0',
                'units.*.market_values.*.value_date'     => 'required|string|max:7',
            ]);
        }

        // ── Ownership-conditional asset field requirements ─────────────
        // Same rule as store() — see assetFieldErrors() docblock. Runs
        // before any write, so a validation failure here never leaves the
        // property partially updated.
        $assetErrors = array_merge(
            $this->assetFieldErrors($base['ownership'] ?? null, $nature, $request->input('units', [])),
            $this->ownerNameErrors($base['ownership'] ?? null, $nature, $request->input('units', []))
        );
        if (!empty($assetErrors)) {
            return back()->withErrors($assetErrors)->withInput();
        }

        // ── Code uniqueness (exclude self) ────────────────────────────
        $code = $base['property_code'] ?? $property->property_code;
        if (empty($code)) {
            // Legacy row that never got a code — generate one now rather
            // than leaving it blank forever.
            $acquisitionDateForCode = $nature === 'unit'
                ? $request->input('acquisition_date')
                : $this->earliestAcquisitionDate($request->input('units', []));
            $code = $this->generatePropertyCode($company, $nature, $acquisitionDateForCode);
        }
        if ($code) {
            $exists = Property::where('company_id', $company->id)
                ->where('property_code', $code)
                ->where('id', '!=', $property->id)
                ->exists();
            if ($exists) {
                return back()->withErrors(['property_code' => 'This property code is already used.'])->withInput();
            }
        }

        // ── Update parent property ────────────────────────────────────
        $propertyData = [
            'property_name' => $base['property_name'],
            'property_code' => $code,
            'ownership'     => $base['ownership'],
            // Only meaningful (and kept) for Usufruct / Managed For Others —
            // see ownershipHidesAssetFields(); nulled out otherwise.
            'owner_name'    => $this->ownershipHidesAssetFields($base['ownership'] ?? null) ? ($base['owner_name'] ?? null) : null,
            'country'       => $base['country'] ?? 'Egypt',
            'governorate'   => $base['governorate'] ?? null,
            'province'      => $base['province'] ?? null,
            'location'      => $base['location'] ?? null,
        ];

        if ($nature === 'unit') {
            $propertyData = array_merge($propertyData, $this->nullAssetFieldsIfHidden([
                'property_category_id'         => $request->input('property_category_id'),
                'property_type_id'             => $request->input('property_type_id'),
                'area'                         => $request->input('area'),
                'unit_of_measurement'          => $request->input('unit_of_measurement'),
                'acquisition_cost'             => $request->input('acquisition_cost'),
                'currency'                     => $request->input('currency', 'EGP'),
                'acquisition_date'             => $request->input('acquisition_date'),
                'book_value'                   => $request->input('book_value'),
                'accumulated_depreciation'     => $request->input('accumulated_depreciation'),
                'monthly_depreciation'         => $request->input('monthly_depreciation'),
                'depreciation_duration_months' => $request->input('depreciation_duration_months'),
            ], $base['ownership'] ?? null));

            // Fix for audit Findings 1/3/4 — refresh the stored base-currency
            // conversion any time the property's own figures/currency/date change.
            $propertyData = array_merge($propertyData, $this->propertyValuationConversion(
                $company->id,
                $company->currency ?: 'EGP',
                $propertyData['acquisition_cost'] ?? null,
                $propertyData['book_value'] ?? null,
                $propertyData['currency'] ?? null,
                $propertyData['acquisition_date'] ?? null
            ));

            // Replace market values: delete all then re-insert. Usufruct/
            // Managed For Others units aren't company assets, so skip
            // re-inserting regardless of what was submitted — this still
            // correctly clears out any market values left over from before
            // an ownership change to Usufruct/Managed For Others.
            $property->marketValues()->delete();
            if (!$this->ownershipHidesAssetFields($base['ownership'] ?? null)) {
                foreach ($request->input('market_values', []) as $mv) {
                    $mvConversion = $this->marketValueConversion(
                        $company->id,
                        $company->currency ?: 'EGP',
                        (float) $mv['market_value'],
                        $propertyData['currency'] ?? 'EGP',
                        $mv['value_date']
                    );
                    PropertyMarketValue::create([
                        'company_id'    => $company->id,
                        'property_id'   => $property->id,
                        'market_value'  => $mv['market_value'],
                        'value_date'    => $mv['value_date'],
                        'notes'         => $mv['notes'] ?? null,
                        'base_amount'   => $mvConversion['base_amount'],
                        'base_currency' => $mvConversion['base_currency'],
                        'fx_rate_used'  => $mvConversion['fx_rate_used'],
                    ]);
                }
            }
        }

        $property->update($propertyData);

        $this->syncPropertyTags($company, $property, $request->input('description_tag_ids', []));

        // ── Update child units (building / land / complex) ─────────────
        if (in_array($nature, ['building', 'land', 'complex'])) {
            $submittedIds = collect($request->input('units', []))->pluck('id')->filter()->all();

            // Fix — removing a unit from the form used to just delete the
            // property_units row and stop there. rent_contracts.property_unit_id
            // and keep_or_sell_analyses.property_unit_id are both
            // ON DELETE SET NULL (not cascade) at the database level, so any
            // contract or analysis tied to the removed unit survived —
            // nulled-out unit reference, still fully active, still
            // generating rent_revenues/rent_collections rows and still
            // counted in the Cash Forecast — for a unit that no longer
            // exists. Explicitly cascade-delete those first, the same way
            // PropertyController::destroy() already does for a whole
            // property. market_values and corporate_expense_allocations
            // are already ON DELETE CASCADE for property_unit_id, so no
            // explicit cleanup is needed for those two.
            $removedUnits = $property->units()->whereNotIn('id', $submittedIds)->get();
            foreach ($removedUnits as $removedUnit) {
                foreach ($removedUnit->rentContracts as $contract) {
                    $contract->revenues()->delete();
                    $contract->collections()->delete();
                    $contract->delete();
                }
                $removedUnit->keepOrSellAnalyses()->delete();
                $removedUnit->delete();
            }

            foreach ($request->input('units', []) as $i => $u) {
                $unitOwnership = !empty($u['ownership']) ? $u['ownership'] : ($base['ownership'] ?? null);

                $unitCode = $u['unit_code'] ?? null;
                if (empty($unitCode)) {
                    $unitCode = $this->generateUnitCode($company, $code);
                }

                $unitData = $this->nullAssetFieldsIfHidden([
                    'company_id'                   => $company->id,
                    'property_id'                  => $property->id,
                    'slot_type'                    => $u['slot_type'],
                    'unit_name'                    => $u['unit_name'],
                    'unit_code'                    => $unitCode,
                    'ownership'                    => $u['ownership'] ?? null,
                    'owner_name'                   => $this->ownershipHidesAssetFields($unitOwnership) ? ($u['owner_name'] ?? null) : null,
                    'location'                     => $u['location'] ?? null,
                    'property_category_id'         => $u['property_category_id'] ?? null,
                    'property_type_id'             => $u['property_type_id'] ?? null,
                    'area'                         => $u['area'] ?? null,
                    'unit_of_measurement'          => $u['unit_of_measurement'] ?? null,
                    'acquisition_cost'             => $u['acquisition_cost'] ?? null,
                    'currency'                     => $u['currency'] ?? 'EGP',
                    'acquisition_date'             => $u['acquisition_date'] ?? null,
                    'book_value'                   => $u['book_value'] ?? null,
                    'accumulated_depreciation'     => $u['slot_type'] === 'land_slot' ? null : ($u['accumulated_depreciation'] ?? null),
                    'monthly_depreciation'         => $u['slot_type'] === 'land_slot' ? null : ($u['monthly_depreciation'] ?? null),
                    'depreciation_duration_months' => $u['slot_type'] === 'land_slot' ? null : ($u['depreciation_duration_months'] ?? null),
                    'is_active'                    => true,
                    'sort_order'                   => $i,
                ], $unitOwnership);

                // Fix for audit Findings 1/3/4 — same conversion as the
                // parent-property path above, applied per unit.
                $unitData = array_merge($unitData, $this->propertyValuationConversion(
                    $company->id,
                    $company->currency ?: 'EGP',
                    $unitData['acquisition_cost'] ?? null,
                    $unitData['book_value'] ?? null,
                    $unitData['currency'] ?? null,
                    $unitData['acquisition_date'] ?? null
                ));

                if (!empty($u['id'])) {
                    $unit = PropertyUnit::find($u['id']);
                    if ($unit) {
                        $unit->update($unitData);
                        $unit->marketValues()->delete();
                    }
                } else {
                    $unit = PropertyUnit::create($unitData);
                }

                // Usufruct/Managed For Others units aren't company assets —
                // skip market valuation entries regardless of what was submitted.
                if (!$this->ownershipHidesAssetFields($unitOwnership)) {
                    foreach ($u['market_values'] ?? [] as $mv) {
                        $mvConversion = $this->marketValueConversion(
                            $company->id,
                            $company->currency ?: 'EGP',
                            (float) $mv['market_value'],
                            $unitData['currency'] ?? 'EGP',
                            $mv['value_date']
                        );
                        PropertyMarketValue::create([
                            'company_id'       => $company->id,
                            'property_unit_id' => $unit->id,
                            'market_value'     => $mv['market_value'],
                            'value_date'       => $mv['value_date'],
                            'notes'            => $mv['notes'] ?? null,
                            'base_amount'      => $mvConversion['base_amount'],
                            'base_currency'    => $mvConversion['base_currency'],
                            'fx_rate_used'     => $mvConversion['fx_rate_used'],
                        ]);
                    }
                }
            }
        }

        return redirect()
            ->route('company.properties.index', $company->id)
            ->with('success', 'Property "' . $property->property_name . '" updated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        // Fix — VERO is a management system, not an accounting/archival
        // system: deleting a property must permanently remove it and
        // everything that depended on it (contracts, revenues, collections,
        // installments, expenses, market values, tags, Keep-or-Sell
        // analyses, corporate expense allocation snapshots). The database's
        // own ON DELETE CASCADE foreign keys already cover all of this, but
        // this method deletes everything explicitly too (belt and
        // suspenders) so the result is correct even if foreign key
        // enforcement is ever off in a given environment. Wrapped in a
        // transaction so a failure partway through leaves nothing
        // half-deleted.
        DB::transaction(function () use ($property) {
            // Rent contracts directly on the property (standalone unit).
            foreach ($property->rentContracts as $contract) {
                $contract->revenues()->delete();
                $contract->collections()->delete();
                $contract->delete();
            }

            // Every child unit (building / land / complex) and its own
            // contracts, market values, Keep-or-Sell analyses, and
            // corporate expense allocation snapshots.
            foreach ($property->units as $unit) {
                foreach ($unit->rentContracts as $contract) {
                    $contract->revenues()->delete();
                    $contract->collections()->delete();
                    $contract->delete();
                }
                $unit->marketValues()->delete();
                $unit->keepOrSellAnalyses()->delete();
                $unit->corporateExpenseAllocations()->delete();
            }

            // Installments — dues, then the plan itself.
            $property->installmentDues()->delete();
            if ($property->installmentPlan) {
                $property->installmentPlan->delete();
            }

            // Property expenses — payments and the forecasted payment
            // schedule first, then the expense.
            foreach ($property->expenses as $expense) {
                $expense->payments()->delete();
                $expense->paymentSchedule()->delete();
                $expense->delete();
            }

            $property->marketValues()->delete();
            $property->tags()->detach();
            $property->keepOrSellAnalyses()->delete();
            $property->corporateExpenseAllocations()->delete();

            // Child units, then the property itself — both now genuine
            // hard deletes (SoftDeletes removed from both models).
            $property->units()->delete();
            $property->delete();
        });

        return back()->with('success', 'Property and all related records permanently deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Fix for audit finding C-2 — authorizeCompany() alone only confirms the
     * logged-in user belongs to {company} in the URL. It never confirmed
     * that {property} (resolved by Laravel's implicit route-model binding,
     * with no company filter applied) actually belongs to that SAME
     * company. A user could pass their own valid company ID to satisfy
     * authorizeCompany(), while supplying another company's property ID,
     * and view/edit/delete it. 404 (not 403) so this doesn't confirm to an
     * attacker that the ID exists at all.
     */
    private function authorizeProperty(Company $company, Property $property): void
    {
        abort_unless($property->company_id === $company->id, 404);
    }

    /**
     * @param  array<int, mixed>  $tagIds
     */
    private function syncPropertyTags(Company $company, Property $property, array $tagIds): void
    {
        $ids = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $property->tags()->sync([]);

            return;
        }

        $validIds = Tag::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->pluck('id');

        $property->tags()->sync($validIds);
    }

    private function ownershipOptions(): array
    {
        return [
            ['value' => 'fully_owned',  'label' => 'Fully Owned'],
            ['value' => 'installments', 'label' => 'Owned with Installments'],
            ['value' => 'usufruct',     'label' => 'Usufruct (Right of Use)'],
            ['value' => 'managed',      'label' => 'Managed For Others'],
        ];
    }

    // ── Ownership-driven asset field rules ─────────────────────────────
    // A unit/property is only ever a company asset (and therefore carries
    // acquisition/depreciation/valuation figures) under Fully Owned or
    // Owned with Installments. Usufruct and Managed For Others units are
    // not owned by the company, so those fields don't apply at all.
    private function ownershipRequiresAssetFields(?string $ownership): bool
    {
        return in_array($ownership, ['fully_owned', 'installments'], true);
    }

    private function ownershipHidesAssetFields(?string $ownership): bool
    {
        return in_array($ownership, ['usufruct', 'managed'], true);
    }

    /**
     * Manually validates the ownership-conditional asset fields (Acquisition
     * Cost, Acquisition Date, Depreciation Duration) for the main property
     * and every child unit, using each unit's EFFECTIVE ownership — its own
     * override if set, otherwise the parent's. Laravel's declarative
     * required_if can't express "required if THIS field, or a fallback
     * field, equals X", so this runs as a manual second pass after the
     * main $request->validate() calls, per the confirmed business rule
     * that Building/Land/Complex parents carry no financials of their own
     * — an inherited-ownership unit's requirement follows the parent's.
     *
     * @param  array<int,array<string,mixed>>  $units
     * @return array<string,string>  validation-error-shaped [field => message]
     */
    private function assetFieldErrors(?string $baseOwnership, string $nature, array $units = []): array
    {
        $errors = [];

        $check = function (string $prefix, ?string $ownership, ?string $slotType, array $data) use (&$errors) {
            // Land never depreciates and never collects these fields at
            // all — nothing to require, regardless of ownership.
            if ($slotType === 'land_slot') {
                return;
            }
            if (!$this->ownershipRequiresAssetFields($ownership)) {
                return;
            }

            if ($data['acquisition_cost'] === null || $data['acquisition_cost'] === '') {
                $errors["{$prefix}acquisition_cost"] = 'Acquisition Cost is required for this ownership type.';
            }
            if ($data['acquisition_date'] === null || $data['acquisition_date'] === '') {
                $errors["{$prefix}acquisition_date"] = 'Acquisition Date is required for this ownership type.';
            }
            $duration = $data['depreciation_duration_months'];
            if ($duration === null || $duration === '' || (int) $duration < 1) {
                $errors["{$prefix}depreciation_duration_months"] = 'Depreciation Duration must be at least 1 month for this ownership type.';
            }
        };

        // Standalone Unit-nature property carries its own financials
        // directly (never a land_slot — land is never nature=unit).
        if ($nature === 'unit') {
            $check('', $baseOwnership, null, [
                'acquisition_cost'             => request()->input('acquisition_cost'),
                'acquisition_date'             => request()->input('acquisition_date'),
                'depreciation_duration_months' => request()->input('depreciation_duration_months'),
            ]);
        }

        foreach ($units as $i => $u) {
            $effectiveOwnership = !empty($u['ownership']) ? $u['ownership'] : $baseOwnership;
            $check("units.{$i}.", $effectiveOwnership, $u['slot_type'] ?? null, [
                'acquisition_cost'             => $u['acquisition_cost'] ?? null,
                'acquisition_date'             => $u['acquisition_date'] ?? null,
                'depreciation_duration_months' => $u['depreciation_duration_months'] ?? null,
            ]);
        }

        return $errors;
    }

    /**
     * Mirrors assetFieldErrors() for the new Owner Name field: required
     * exactly when the (effective) ownership is Usufruct or Managed For
     * Others — the company doesn't own the asset in those cases, so we
     * need to record who does. Runs as a manual second pass for the same
     * reason as assetFieldErrors() — the requirement depends on either
     * the unit's own ownership override or its parent's, which Laravel's
     * declarative required_if can't express.
     *
     * @param  array<int,array<string,mixed>>  $units
     * @return array<string,string>  validation-error-shaped [field => message]
     */
    private function ownerNameErrors(?string $baseOwnership, string $nature, array $units = []): array
    {
        $errors = [];

        if ($nature === 'unit' && $this->ownershipHidesAssetFields($baseOwnership)) {
            $ownerName = request()->input('owner_name');
            if ($ownerName === null || trim((string) $ownerName) === '') {
                $errors['owner_name'] = 'Owner Name is required for Usufruct or Managed For Others.';
            }
        }

        foreach ($units as $i => $u) {
            $effectiveOwnership = !empty($u['ownership']) ? $u['ownership'] : $baseOwnership;
            if ($this->ownershipHidesAssetFields($effectiveOwnership)) {
                $ownerName = $u['owner_name'] ?? null;
                if ($ownerName === null || trim((string) $ownerName) === '') {
                    $errors["units.{$i}.owner_name"] = 'Owner Name is required for Usufruct or Managed For Others.';
                }
            }
        }

        return $errors;
    }

    /**
     * Usufruct and Managed For Others units are not company assets — force
     * every asset/depreciation/valuation field to null before saving,
     * regardless of what was submitted (the frontend hides these fields
     * for that ownership, but a stale value from a prior ownership change
     * shouldn't linger in the database). Mirrors the existing land_slot
     * "no depreciation" nulling already applied elsewhere in this
     * controller. `currency` is intentionally left alone — it's a NOT
     * NULL column with a DB default, and irrelevant once cost is null.
     */
    private function nullAssetFieldsIfHidden(array $data, ?string $ownership): array
    {
        if (!$this->ownershipHidesAssetFields($ownership)) {
            return $data;
        }

        return array_merge($data, [
            'acquisition_cost'             => null,
            'acquisition_date'             => null,
            'book_value'                   => null,
            'accumulated_depreciation'     => null,
            'monthly_depreciation'         => null,
            'depreciation_duration_months' => null,
        ]);
    }

    private function egyptianGovernorates(): array
    {
        return [
            'Cairo', 'Giza', 'Alexandria', 'Dakahlia', 'Red Sea', 'Beheira',
            'Fayoum', 'Gharbia', 'Ismailia', 'Menofia', 'Minya', 'Qaliubiya',
            'New Valley', 'Suez', 'Aswan', 'Assiut', 'Beni Suef', 'Port Said',
            'Damietta', 'Sharkia', 'South Sinai', 'Kafr El Sheikh', 'Matrouh',
            'Luxor', 'Qena', 'North Sinai', 'Sohag',
        ];
    }

    private function uomOptions(): array
    {
        return ['sqm', 'ft²', 'feddan', 'hectare', 'acre'];
    }

    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED'];
    }
}