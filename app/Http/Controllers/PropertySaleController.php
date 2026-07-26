<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertySale;
use App\Models\PropertySaleDue;
use App\Models\PropertyUnit;
use App\Models\RentContract;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

/**
 * Phase 1 of the "Record Sale" feature (confirmed July 2026). Previously
 * the only way to remove a property from the system was a hard delete
 * (PropertyController::destroy()), which wipes its entire history — wrong
 * for a real sale, where the historical revenue/expense trail needs to
 * survive for reporting. This controller records an actual sale instead:
 * it terminates any running contract, stops future revenue/collections
 * from the sale date forward, computes realized gain/loss, and marks the
 * unit `sold_at` rather than deleting anything.
 *
 * Three entry points, one shared engine (recordSale()):
 *   - sellUnit()      — a standalone Unit property
 *   - sellChildUnit() — a single unit inside a Building/Land/Complex
 *   - sellWhole()     — the ENTIRE Building/Land/Complex in one
 *                        transaction: one lump sum, divided by total area
 *                        to get a price/sqm, then allocated to each child
 *                        unit by its own area (last unit absorbs the
 *                        rounding remainder — same pattern already used by
 *                        CorporateExpenseAllocationService::allocate()).
 *
 * Phase 2 (confirmed July 2026, now built): if payment_method =
 * installments, an actual receivable due-date schedule is generated
 * (property_sale_dues, mirroring property_installment_dues but in
 * reverse — money coming IN from a buyer instead of going OUT to a
 * developer), tracked with the same pending/collected/overdue lifecycle,
 * and wired into the Cash Forecast as a Cash-In line. The realized
 * gain/loss is still booked in full on the sale date regardless of
 * payment method (confirmed) — only the cash receipt is spread over time.
 *
 * Phase 3 (confirmed July 2026): the schedule is no longer auto-generated
 * from a down-payment % / count / interval combination. The user now
 * enters the exact schedule directly via a repeater of {amount, date}
 * rows — whatever they've actually agreed with the buyer — and the rows
 * must sum exactly to the sale price. For sellWhole(), the SAME rows
 * (entered once, against the total lump sum) are scaled down
 * proportionally into each unit's own schedule using that unit's price
 * share, with the last row absorbing any rounding remainder — identical
 * in spirit to the per-unit price allocation already used for sale_price
 * itself. See scaleInstallmentRows() / validateInstallmentTotal().
 */
class PropertySaleController extends Controller
{
    use AuthorizesCompany;

    private function authorizeProperty(Company $company, Property $property): void
    {
        abort_unless($property->company_id === $company->id, 404);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL — standalone Unit
    // ═══════════════════════════════════════════════════════════════════
    public function sellUnit(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        if ($property->nature !== 'unit') {
            return back()->withErrors(['sale' => 'Use "Sell Entire Property" for a Building/Land/Complex.'])->withInput();
        }
        if ($property->sold_at) {
            return back()->withErrors(['sale' => 'This property is already marked as sold.'])->withInput();
        }

        $data = $this->validateSale($request);

        $sale = $this->recordSale($company, $property, null, $data, null, null, null);

        return redirect()->route('company.properties.index', $company->id)
            ->with('success', $this->successMessage($property->property_name, $sale));
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL PAGE — standalone Unit (moved out of the Properties Index
    // modal into its own dedicated page)
    // ═══════════════════════════════════════════════════════════════════
    public function sellUnitForm(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($property->nature === 'unit', 404);
        abort_if($property->sold_at, 404);

        return Inertia::render('Properties/Sell/Index', [
            'company'    => $company,
            'property'   => $property,
            'unit'       => null,
            'mode'       => 'unit',
            'unitsCount' => null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL — a single child unit inside a Building/Land/Complex
    // ═══════════════════════════════════════════════════════════════════
    public function sellChildUnit(Request $request, Company $company, Property $property, PropertyUnit $unit)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($unit->property_id === $property->id, 404);

        if (in_array($property->nature, ['unit'], true)) {
            return back()->withErrors(['sale' => 'This property has no child units.'])->withInput();
        }
        if ($unit->sold_at) {
            return back()->withErrors(['sale' => 'This unit is already marked as sold.'])->withInput();
        }

        $data = $this->validateSale($request);

        $sale = $this->recordSale($company, $property, $unit, $data, null, null, null);

        return redirect()->route('company.properties.index', $company->id)
            ->with('success', $this->successMessage($unit->unit_name, $sale));
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL PAGE — a single child unit inside a Building/Land/Complex
    // ═══════════════════════════════════════════════════════════════════
    public function sellChildUnitForm(Company $company, Property $property, PropertyUnit $unit)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($unit->property_id === $property->id, 404);
        abort_if($unit->sold_at, 404);

        return Inertia::render('Properties/Sell/Index', [
            'company'    => $company,
            'property'   => $property,
            'unit'       => $unit,
            'mode'       => 'unit',
            'unitsCount' => null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL PAGE — entire Building/Land/Complex
    // ═══════════════════════════════════════════════════════════════════
    public function sellWholeForm(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless(in_array($property->nature, ['building', 'land', 'complex'], true), 404);

        $unitsCount = $property->units()->whereNull('sold_at')->count();

        return Inertia::render('Properties/Sell/Index', [
            'company'    => $company,
            'property'   => $property,
            'unit'       => null,
            'mode'       => 'whole',
            'unitsCount' => $unitsCount,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SELL — entire Building/Land/Complex in one transaction
    // ═══════════════════════════════════════════════════════════════════
    public function sellWhole(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);

        if (!in_array($property->nature, ['building', 'land', 'complex'], true)) {
            return back()->withErrors(['sale' => 'Use "Sell Unit" for a standalone property.'])->withInput();
        }

        $data = $request->validate([
            'sale_date'                  => 'required|date',
            'buyer_name'                 => 'nullable|string|max:255',
            'total_sale_price'           => 'required|numeric|min:0.01',
            'currency'                   => 'nullable|string|max:10',
            'selling_costs_pct'          => 'nullable|numeric|min:0|max:100',
            'payment_method'             => 'required|in:cash,installments',
            'payment_terms_notes'        => 'nullable|string|max:2000',
            // Manually entered schedule (Phase 3) — exact {amount, date}
            // rows the user agreed with the buyer, entered once against the
            // total lump sum and then scaled per unit below.
            'installment_rows'           => 'nullable|array',
            'installment_rows.*.amount'  => 'required_with:installment_rows|numeric|min:0.01',
            'installment_rows.*.date'    => 'required_with:installment_rows|date',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $this->validateInstallmentTotal($data, (float) $data['total_sale_price']);

        $units = $property->units()->whereNull('sold_at')->orderBy('id')->get();

        if ($units->isEmpty()) {
            return back()->withErrors(['sale' => 'Every unit in this property is already sold — nothing left to sell.'])->withInput();
        }

        // ── Allocate the lump sum by area — same rule as
        // CorporateExpenseAllocationService::allocate(): proportional to
        // area, equal split if area data is missing/zero anywhere, last
        // unit absorbs the rounding remainder so the total reconciles
        // exactly to what was entered.
        $totalArea     = (float) $units->sum('area');
        $missingArea   = $units->contains(fn ($u) => (float) $u->area <= 0);
        $useEqualSplit = $totalArea <= 0 || $missingArea;
        $count         = $units->count();
        $totalPrice    = (float) $data['total_sale_price'];
        $pricePerSqm   = $useEqualSplit ? null : round($totalPrice / $totalArea, 2);

        $saleBatchId = (string) Str::uuid();
        $sales       = [];
        $warnings    = [];
        $runningTotal = 0.0;
        $idx = 0;

        foreach ($units as $unit) {
            $idx++;
            $isLast = $idx === $count;

            if ($isLast) {
                $unitPrice = round($totalPrice - $runningTotal, 2);
            } else {
                $share     = $useEqualSplit ? (1 / $count) : ((float) $unit->area / $totalArea);
                $unitPrice = round($totalPrice * $share, 2);
            }
            $runningTotal += $unitPrice;

            $unitData = [
                'sale_date'              => $data['sale_date'],
                'buyer_name'             => $data['buyer_name'] ?? null,
                'sale_price'             => $unitPrice,
                'currency'               => $data['currency'] ?? null,
                'selling_costs_pct'      => $data['selling_costs_pct'] ?? 0,
                'payment_method'         => $data['payment_method'],
                'payment_terms_notes'    => $data['payment_terms_notes'] ?? null,
                // Scale the master schedule (entered once against the total
                // lump sum) down to this unit's own price share, same
                // proportional-allocation rule used for $unitPrice itself —
                // last row absorbs the rounding remainder so this unit's
                // rows always sum to exactly $unitPrice.
                'installment_rows'       => $data['payment_method'] === PropertySale::PAYMENT_INSTALLMENTS
                    ? $this->scaleInstallmentRows($data['installment_rows'] ?? [], $unitPrice, $totalPrice)
                    : [],
                'notes'                  => $data['notes'] ?? null,
            ];

            $sale = $this->recordSale($company, $property, $unit, $unitData, $saleBatchId, (float) $unit->area, $pricePerSqm);
            $sales[] = $sale;
            if ($sale->warnings) {
                $warnings[] = "{$unit->unit_name}: {$sale->warnings}";
            }
        }

        $msg = count($sales) . ' unit(s) sold as part of "' . $property->property_name . '" for a total of '
            . number_format($totalPrice, 2) . ' ' . strtoupper($data['currency'] ?? $company->currency ?: 'EGP') . '.';
        if ($useEqualSplit) {
            $msg .= ' Note: area data was missing on at least one unit, so the price was split equally rather than by area.';
        }
        if (!empty($warnings)) {
            $msg .= ' ⚠ ' . implode(' | ', $warnings);
        }

        return redirect()->route('company.properties.index', $company->id)->with('success', $msg);
    }

    // ═══════════════════════════════════════════════════════════════════
    // MARK A RECEIVABLE DUE AS COLLECTED (Phase 2)
    // ═══════════════════════════════════════════════════════════════════
    public function markDueCollected(Request $request, Company $company, PropertySale $sale, PropertySaleDue $due)
    {
        $this->authorizeCompany($company);
        abort_unless($sale->company_id === $company->id, 404);
        abort_unless($due->property_sale_id === $sale->id, 404);

        $data = $request->validate([
            'collected_date' => 'required|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $due->update([
            'status'         => PropertySaleDue::STATUS_COLLECTED,
            'collected_date' => $data['collected_date'],
            'notes'          => $data['notes'] ?? $due->notes,
        ]);

        return back()->with('success', 'Receivable marked collected.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // SHARED VALIDATION — single-unit sales (sellUnit / sellChildUnit)
    // ═══════════════════════════════════════════════════════════════════
    private function validateSale(Request $request): array
    {
        $data = $request->validate([
            'sale_date'                  => 'required|date',
            'buyer_name'                 => 'nullable|string|max:255',
            'sale_price'                 => 'required|numeric|min:0.01',
            'currency'                   => 'nullable|string|max:10',
            'selling_costs_pct'          => 'nullable|numeric|min:0|max:100',
            'payment_method'             => 'required|in:cash,installments',
            'payment_terms_notes'        => 'nullable|string|max:2000',
            // Installment schedule (Phase 3) — only meaningful when
            // payment_method = installments; ignored otherwise. The user
            // fills the repeater with the exact amount + date they agreed
            // with the buyer for each payment.
            'installment_rows'           => 'nullable|array',
            'installment_rows.*.amount'  => 'required_with:installment_rows|numeric|min:0.01',
            'installment_rows.*.date'    => 'required_with:installment_rows|date',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $this->validateInstallmentTotal($data, (float) $data['sale_price']);

        return $data;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Confirmed product decision (July 2026 session): the installment
    // schedule is entered manually, row by row, so — unlike the old
    // auto-generated count/interval schedule which reconciled to the
    // sale price by construction — nothing stops the rows from not
    // adding up unless it's checked explicitly. Enforced here rather than
    // just client-side so a stale/tampered submission can't slip through.
    // A small rounding tolerance (1 cent) absorbs float/display rounding.
    // ═══════════════════════════════════════════════════════════════════
    private function validateInstallmentTotal(array $data, float $price): void
    {
        if (($data['payment_method'] ?? null) !== PropertySale::PAYMENT_INSTALLMENTS) {
            return;
        }

        $rows = $data['installment_rows'] ?? [];
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'installment_rows' => 'Add at least one installment row (amount + date) — payment method is Installments.',
            ]);
        }

        $sum = round(array_sum(array_map(fn ($r) => (float) $r['amount'], $rows)), 2);
        $price = round($price, 2);

        if (abs($sum - $price) > 0.01) {
            throw ValidationException::withMessages([
                'installment_rows' => "Installment rows total {$sum} but must equal the sale price of {$price} exactly.",
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Scales a master {amount, date} schedule down to one unit's price
    // share of the total — same proportional-allocation rule already used
    // for $unitPrice in sellWhole(): amount * (unitShare / totalPrice),
    // with the LAST row absorbing the rounding remainder so this unit's
    // rows always sum to exactly $unitShare (never off by a cent from
    // float rounding across many rows). Dates are carried over unchanged
    // — every unit is due on the same dates, just for its own share.
    // ═══════════════════════════════════════════════════════════════════
    private function scaleInstallmentRows(array $rows, float $unitShare, float $totalPrice): array
    {
        if ($totalPrice <= 0 || empty($rows)) {
            return [];
        }

        $scaled = [];
        $running = 0.0;
        $count = count($rows);

        foreach (array_values($rows) as $i => $row) {
            $isLast = $i === $count - 1;
            $amount = $isLast
                ? round($unitShare - $running, 2)
                : round(((float) $row['amount']) * ($unitShare / $totalPrice), 2);
            $running += $amount;

            $scaled[] = ['amount' => $amount, 'date' => $row['date']];
        }

        return $scaled;
    }

    private function successMessage(string $name, PropertySale $sale): string
    {
        $msg = "\"{$name}\" marked as sold for " . number_format((float) $sale->sale_price, 2) . ' ' . $sale->currency . '.';
        if ($sale->realized_gain_loss !== null) {
            $gain = (float) $sale->realized_gain_loss;
            $msg .= ' Realized ' . ($gain >= 0 ? 'gain' : 'loss') . ' of '
                . number_format(abs($gain), 2) . ' ' . $sale->base_currency . '.';
        }
        if ($sale->warnings) {
            $msg .= ' ⚠ ' . $sale->warnings;
        }
        return $msg;
    }

    // ═══════════════════════════════════════════════════════════════════
    // THE ENGINE — records one unit's sale (standalone Property OR a
    // PropertyUnit), whichever $unit is. Always run inside a transaction
    // since it touches 4+ tables and must not partially apply.
    // ═══════════════════════════════════════════════════════════════════
    private function recordSale(
        Company $company,
        Property $property,
        ?PropertyUnit $unit,
        array $data,
        ?string $saleBatchId,
        ?float $areaAtSale,
        ?float $pricePerSqm
    ): PropertySale {
        return DB::transaction(function () use ($company, $property, $unit, $data, $saleBatchId, $areaAtSale, $pricePerSqm) {
            $target = $unit ?? $property; // the actual sellable record — same field names on both models

            $currency = strtoupper($data['currency'] ?? $target->currency ?: 'EGP');
            $baseCurrency = strtoupper($company->currency ?: 'EGP');
            $sellingCostsPct = (float) ($data['selling_costs_pct'] ?? 0);
            $salePrice = (float) $data['sale_price'];
            $netProceeds = round($salePrice * (1 - $sellingCostsPct / 100), 2);

            $fx = app(CurrencyConversionService::class);
            $conversion = $fx->convert($company->id, $baseCurrency, $netProceeds, $currency, Carbon::parse($data['sale_date']));

            $bookValueBase = $target->book_value_base_amount !== null ? (float) $target->book_value_base_amount : null;
            $realizedGainLoss = ($conversion['base_amount'] !== null && $bookValueBase !== null)
                ? round($conversion['base_amount'] - $bookValueBase, 2)
                : null;

            // ── Terminate the running contract (if any) and stop future
            // revenue/collections from the sale date forward ─────────────
            $contractQuery = $unit
                ? RentContract::where('property_unit_id', $unit->id)
                : RentContract::where('property_id', $property->id)->whereNull('property_unit_id');

            $contract = $contractQuery->where('status', RentContract::STATUS_RUNNING)->first();
            $warnings = null;

            if ($contract) {
                $saleDate = Carbon::parse($data['sale_date']);
                // The whole month of sale still counts as earned revenue
                // (this app doesn't do daily proration anywhere else, e.g.
                // annual increases apply at month boundaries) — only
                // strictly LATER months are cut off.
                $cutoff = $saleDate->copy()->startOfMonth()->addMonth();

                $contract->update([
                    'status'            => RentContract::STATUS_TERMINATED,
                    'terminated_date'   => $data['sale_date'],
                    'termination_notes' => 'Auto-terminated — unit sold on ' . $saleDate->format('d/m/Y') . '.',
                ]);

                $contract->revenues()->where('revenue_date', '>=', $cutoff->toDateString())->delete();
                $contract->collections()->where('period_from', '>=', $cutoff->toDateString())->delete();

                // A collection tranche that STRADDLES the sale month
                // (starts before the cutoff, ends on/after it) is left
                // untouched rather than guessed at — surfaced as a warning
                // for manual review instead of silently prorating it.
                $straddling = $contract->collections()
                    ->where('period_from', '<', $cutoff->toDateString())
                    ->where('period_to', '>=', $cutoff->toDateString())
                    ->get(['id', 'period_from', 'period_to', 'collection_amount', 'status']);

                if ($straddling->isNotEmpty()) {
                    $labels = $straddling->map(fn ($c) => Carbon::parse($c->period_from)->format('d/m/Y') . '–' . Carbon::parse($c->period_to)->format('d/m/Y'))->implode(', ');
                    $warnings = "Collection period(s) {$labels} span the sale date and were left as-is — review manually.";
                }
            }

            $sale = PropertySale::create([
                'company_id'                     => $company->id,
                'sale_batch_id'                   => $saleBatchId,
                'property_id'                     => $property->id,
                'property_unit_id'                 => $unit?->id,
                'sale_date'                        => $data['sale_date'],
                'buyer_name'                       => $data['buyer_name'] ?? null,
                'area_at_sale'                     => $areaAtSale ?? $target->area,
                'price_per_sqm'                    => $pricePerSqm,
                'sale_price'                       => $salePrice,
                'currency'                         => $currency,
                'selling_costs_pct'                => $sellingCostsPct,
                'net_sale_proceeds'                => $netProceeds,
                'net_sale_proceeds_base_amount'    => $conversion['base_amount'],
                'book_value_base_amount_at_sale'   => $bookValueBase,
                'realized_gain_loss'               => $realizedGainLoss,
                'base_currency'                    => $conversion['base_currency'],
                'fx_rate_used'                      => $conversion['fx_rate_used'],
                'payment_method'                    => $data['payment_method'],
                'payment_terms_notes'               => $data['payment_terms_notes'] ?? null,
                'rent_contract_id'                  => $contract?->id,
                'warnings'                           => $warnings,
                'notes'                              => $data['notes'] ?? null,
                'created_by'                         => auth()->id(),
            ]);

            $target->update(['sold_at' => $data['sale_date']]);

            if ($data['payment_method'] === PropertySale::PAYMENT_INSTALLMENTS) {
                $this->generateSaleDues($sale, $data);
            }

            return $sale;
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 3 — installment receivable schedule, inserted directly from
    // the manually-entered {amount, date} repeater rows. For sellUnit() /
    // sellChildUnit() these are exactly what the user typed; for
    // sellWhole() they've already been scaled to this unit's share by
    // scaleInstallmentRows() before reaching here. Rows are sorted by
    // date so the schedule always displays chronologically regardless of
    // the order the user added them in.
    // ═══════════════════════════════════════════════════════════════════
    private function generateSaleDues(PropertySale $sale, array $data): void
    {
        $rows = collect($data['installment_rows'] ?? [])
            ->filter(fn ($r) => (float) ($r['amount'] ?? 0) > 0 && !empty($r['date']))
            ->sortBy(fn ($r) => Carbon::parse($r['date'])->toDateString())
            ->values();

        $sortOrder = 0;
        foreach ($rows as $row) {
            PropertySaleDue::create([
                'company_id'       => $sale->company_id,
                'property_sale_id' => $sale->id,
                'due_type'         => PropertySaleDue::TYPE_INSTALLMENT,
                'due_date'         => Carbon::parse($row['date'])->toDateString(),
                'amount'           => round((float) $row['amount'], 2),
                'currency'         => $sale->currency,
                'status'           => PropertySaleDue::STATUS_PENDING,
                'sort_order'       => $sortOrder++,
            ]);
        }
    }
}
