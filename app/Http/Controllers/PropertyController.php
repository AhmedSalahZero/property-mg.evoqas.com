<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\PropertyMarketValue;
use App\Models\PropertyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PropertyController extends Controller
{
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
                'units' => fn($q) => $q->with([
                    'propertyCategory:id,category_name',
                    'propertyType:id,type_name',
                    'marketValues' => fn($q) => $q->orderByDesc('value_date')->limit(1),
                    // Running contracts on child units
                    'rentContracts' => fn($q) => $q->where('status', 'running')
                                                   ->select('id','property_id','property_unit_id','status'),
                ]),
                'marketValues' => fn($q) => $q->orderByDesc('value_date')->limit(1),
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
            'country'       => 'nullable|string|max:100',
            'governorate'   => 'nullable|string|max:150',
            'province'      => 'nullable|string|max:150',
            'location'      => 'nullable|string|max:500',
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

        // ── Auto-generate code if blank ───────────────────────────────
        $code = $base['property_code'] ?? null;
        if (empty($code)) {
            $code = strtoupper(substr($nature, 0, 3)) . '-' . strtoupper(Str::random(6));
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
            'country'       => $base['country'] ?? 'Egypt',
            'governorate'   => $base['governorate'] ?? null,
            'province'      => $base['province'] ?? null,
            'location'      => $base['location'] ?? null,
            'is_active'     => true,
            'sort_order'    => Property::where('company_id', $company->id)->max('sort_order') + 1,
        ];

        // For unit nature, add financials to parent
        if ($nature === 'unit') {
            $propertyData = array_merge($propertyData, [
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
            ]);
        }

        $property = Property::create($propertyData);

        // ── Market values for standalone unit ────────────────────────
        if ($nature === 'unit') {
            foreach ($request->input('market_values', []) as $mv) {
                PropertyMarketValue::create([
                    'company_id'   => $company->id,
                    'property_id'  => $property->id,
                    'market_value' => $mv['market_value'],
                    'value_date'   => $mv['value_date'],
                    'notes'        => $mv['notes'] ?? null,
                ]);
            }
        }

        // ── Child units for building / land / complex ─────────────────
        if (in_array($nature, ['building', 'land', 'complex'])) {
            foreach ($request->input('units', []) as $i => $u) {
                $unit = PropertyUnit::create([
                    'company_id'                   => $company->id,
                    'property_id'                  => $property->id,
                    'slot_type'                    => $u['slot_type'],
                    'unit_name'                    => $u['unit_name'],
                    'unit_code'                    => $u['unit_code'] ?? null,
                    'ownership'                    => $u['ownership'] ?? null,
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
                ]);

                foreach ($u['market_values'] ?? [] as $mv) {
                    PropertyMarketValue::create([
                        'company_id'       => $company->id,
                        'property_unit_id' => $unit->id,
                        'market_value'     => $mv['market_value'],
                        'value_date'       => $mv['value_date'],
                        'notes'            => $mv['notes'] ?? null,
                    ]);
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

        $property->load([
            'propertyCategory',
            'propertyType',
            'marketValues' => fn($q) => $q->orderBy('value_date'),
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
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $nature = $property->nature; // nature is immutable after creation

        // ── Base validation ───────────────────────────────────────────
        $base = $request->validate([
            'property_name' => 'required|string|max:255',
            'property_code' => 'nullable|string|max:100',
            'ownership'     => 'required|in:fully_owned,installments,usufruct,managed',
            'country'       => 'nullable|string|max:100',
            'governorate'   => 'nullable|string|max:150',
            'province'      => 'nullable|string|max:150',
            'location'      => 'nullable|string|max:500',
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

        // ── Code uniqueness (exclude self) ────────────────────────────
        $code = $base['property_code'] ?? $property->property_code;
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
            'country'       => $base['country'] ?? 'Egypt',
            'governorate'   => $base['governorate'] ?? null,
            'province'      => $base['province'] ?? null,
            'location'      => $base['location'] ?? null,
        ];

        if ($nature === 'unit') {
            $propertyData = array_merge($propertyData, [
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
            ]);

            // Replace market values: delete all then re-insert
            $property->marketValues()->delete();
            foreach ($request->input('market_values', []) as $mv) {
                PropertyMarketValue::create([
                    'company_id'   => $company->id,
                    'property_id'  => $property->id,
                    'market_value' => $mv['market_value'],
                    'value_date'   => $mv['value_date'],
                    'notes'        => $mv['notes'] ?? null,
                ]);
            }
        }

        $property->update($propertyData);

        // ── Update child units (building / land / complex) ─────────────
        if (in_array($nature, ['building', 'land', 'complex'])) {
            $request->validate([
                'units'                                  => 'nullable|array',
                'units.*.id'                             => 'nullable|integer',
                'units.*.unit_name'                      => 'required|string|max:255',
                'units.*.slot_type'                      => 'required|in:built_unit,land_slot',
                'units.*.unit_code'                      => 'nullable|string|max:100',
                'units.*.ownership'                      => 'nullable|in:fully_owned,installments,usufruct,managed',
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

            $submittedIds = collect($request->input('units', []))->pluck('id')->filter()->all();

            // Delete units no longer in the list
            $property->units()->whereNotIn('id', $submittedIds)->delete();

            foreach ($request->input('units', []) as $i => $u) {
                $unitData = [
                    'company_id'                   => $company->id,
                    'property_id'                  => $property->id,
                    'slot_type'                    => $u['slot_type'],
                    'unit_name'                    => $u['unit_name'],
                    'unit_code'                    => $u['unit_code'] ?? null,
                    'ownership'                    => $u['ownership'] ?? null,
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
                ];

                if (!empty($u['id'])) {
                    $unit = PropertyUnit::find($u['id']);
                    if ($unit) {
                        $unit->update($unitData);
                        $unit->marketValues()->delete();
                    }
                } else {
                    $unit = PropertyUnit::create($unitData);
                }

                foreach ($u['market_values'] ?? [] as $mv) {
                    PropertyMarketValue::create([
                        'company_id'       => $company->id,
                        'property_unit_id' => $unit->id,
                        'market_value'     => $mv['market_value'],
                        'value_date'       => $mv['value_date'],
                        'notes'            => $mv['notes'] ?? null,
                    ]);
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
        $property->delete(); // soft delete — cascade handled by model events if needed
        return back()->with('success', 'Property deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════
    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }

    private function ownershipOptions(): array
    {
        return [
            ['value' => 'fully_owned',  'label' => 'Fully Owned'],
            ['value' => 'installments', 'label' => 'Owned with Installments'],
            ['value' => 'usufruct',     'label' => 'Usufruct (Right of Use)'],
            ['value' => 'managed',      'label' => 'Managed'],
        ];
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