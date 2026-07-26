<?php

namespace App\Models;

use App\Services\CurrencyConversionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyInstallmentPlan extends Model
{
    protected $fillable = [
        'company_id',
        'property_id',
        'installment_type',
        'currency',
        'delivery_date',
        'ready_to_use_date',
        'signing_amount',
        'signing_date',
        'reservation_amount',
        'reservation_date',
        'installment_rows',
        'has_annual',
        'annual_start_date',
        'annual_amount',
        'annual_count',
        'has_delivery',
        'delivery_start_date',
        'delivery_amount',
        'delivery_count',
        'delivery_interval',
        'has_maintenance',
        'maintenance_start_date',
        'maintenance_amount',
        'maintenance_count',
        'maintenance_interval',
        'created_by',
    ];

    protected $casts = [
        'installment_rows'    => 'array',
        'has_annual'          => 'boolean',
        'has_delivery'        => 'boolean',
        'has_maintenance'     => 'boolean',
        'signing_amount'      => 'decimal:2',
        'reservation_amount'  => 'decimal:2',
        'annual_amount'       => 'decimal:2',
        'delivery_amount'     => 'decimal:2',
        'maintenance_amount'  => 'decimal:2',
        'annual_count'        => 'integer',
        'delivery_count'      => 'integer',
        'maintenance_count'   => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(PropertyInstallmentDue::class, 'plan_id')->orderBy('due_date')->orderBy('sort_order');
    }

    // ── Schedule Generation ──────────────────────────────────────────────────

    /**
     * Reconcile property_installment_dues against the plan's current data.
     *
     * For variable type, dues are saved directly by the controller — skip generation.
     *
     * This used to unconditionally `$this->dues()->delete()` every single save,
     * destroying every 'paid' marker and paid_date the moment the plan was
     * edited at all. That was audit finding C2. It now instead reconciles the
     * newly-computed schedule against existing rows, matched by (due_type,
     * due_date) — the natural, stable identity of a generated due:
     *
     *   - A matching row that is already 'paid' is left completely untouched.
     *   - A matching row still 'pending' has its amount/currency/sort_order
     *     refreshed (nothing real has happened against it yet).
     *   - No match → a new 'pending' row is inserted.
     *   - Any row still 'pending' that no longer appears in the freshly
     *     computed schedule (e.g. a repeater row was removed or its count
     *     reduced) is deleted. Rows already 'paid' or 'overdue' are NEVER
     *     removed this way, even if they fall outside the new schedule.
     *
     * Known limitation: matching is by (due_type, due_date). If two separate
     * installment rows in the repeater ever land on the exact same month for
     * the same due_type (an unusual plan shape), they can collide in the
     * matching key. This is a large improvement over full destruction, but
     * isn't a perfect diff — spot-check the due list after unusual repeater
     * edits.
     */
    public function generateDues(): void
    {
        if ($this->installment_type === 'variable') {
            return; // variable dues come from controller directly
        }

        // Fix for audit finding F-2 — the reconciliation below is a
        // get/update/insert/delete sequence against property_installment_dues
        // with no transaction wrapping it. A failure partway through (a bad
        // date, a DB hiccup) could leave the due schedule half-updated —
        // some rows refreshed, some not yet inserted. See the same fix on
        // RentContract::generateSchedules() for the identical reasoning.
        \DB::transaction(function () {
            $this->generateDuesBody();
        });
    }

    private function generateDuesBody(): void
    {
        $currency = $this->currency ?? 'EGP';

        // Fix (July 2026) — the actual date/amount generation (signing,
        // reservation, installment rows, annual, delivery, maintenance) now
        // lives in InstallmentScheduleGenerator, shared with the Investment
        // Decision Tool's Seller/Developer Installments funding path, so
        // both always agree on how a Regular-mode plan turns into dates.
        // Everything below this point — deduplication-by-suffix, sort
        // order, and reconciliation against the database — is unchanged
        // from before the extraction.
        $generatedRows = \App\Services\InstallmentScheduleGenerator::generateRows([
            'signing_amount'          => $this->signing_amount,
            'signing_date'            => $this->signing_date,
            'reservation_amount'      => $this->reservation_amount,
            'reservation_date'        => $this->reservation_date,
            'installment_rows'        => $this->installment_rows,
            'has_annual'              => $this->has_annual,
            'annual_start_date'       => $this->annual_start_date,
            'annual_amount'           => $this->annual_amount,
            'annual_count'            => $this->annual_count,
            'has_delivery'            => $this->has_delivery,
            'delivery_start_date'     => $this->delivery_start_date,
            'delivery_amount'         => $this->delivery_amount,
            'delivery_count'          => $this->delivery_count,
            'delivery_interval'       => $this->delivery_interval,
            'has_maintenance'         => $this->has_maintenance,
            'maintenance_start_date'  => $this->maintenance_start_date,
            'maintenance_amount'      => $this->maintenance_amount,
            'maintenance_count'       => $this->maintenance_count,
            'maintenance_interval'    => $this->maintenance_interval,
        ]);

        $desired = []; // due_type|due_date(#...) => ['due_type'=>, 'due_date'=>, 'amount'=>]
        $order   = []; // same keys => first-seen order
        $sort    = 0;

        foreach ($generatedRows as $row) {
            $key = $row['due_type'] . '|' . $row['due_date'];
            // If two generated rows land on the same (type, date) — e.g. two
            // repeater rows overlapping — keep them distinct by suffixing the
            // key, rather than silently merging/overwriting one with the other.
            while (isset($desired[$key])) {
                $key .= '#';
            }
            $desired[$key] = $row;
            $order[$key]   = $sort++;
        }

        // ── Reconcile against existing rows ─────────────────────────────────
        $existing = $this->dues()->get()
            ->keyBy(fn ($d) => $d->due_type . '|' . $d->due_date->format('Y-m-d'));

        $fx           = app(CurrencyConversionService::class);
        $baseCurrency = $this->company?->currency ?: 'EGP';

        // Fix — identical bug (and identical fix) as
        // RentContract::reconcileCollections(): this used to build $keepIds
        // as it went, insert every brand-new row, and only THEN delete
        // "whereNotIn($keepIds) AND status=pending" — but a freshly-inserted
        // row's id was never added to $keepIds (it didn't exist yet when
        // $keepIds was built), so that final cleanup deleted every due row
        // this same run had just inserted a moment earlier. On any plan
        // with no pre-existing dues (every brand-new installment plan, or
        // any plan whose dues had already been wiped by this exact bug on a
        // previous save), the net result was always zero due rows.
        //
        // Fixed the same way: figure out which EXISTING rows are obsolete
        // (still pending, and no (due_type, due_date) match in the new
        // desired schedule) and delete only those, BEFORE anything new is
        // inserted — so a newly-inserted row can never be mistaken for
        // something to clean up.
        $desiredMatchKeys = collect(array_keys($desired))
            ->map(fn ($key) => rtrim($key, '#'))
            ->unique();

        $obsoleteIds = $existing
            ->reject(fn ($due, $matchKey) => $desiredMatchKeys->contains($matchKey))
            ->where('status', 'pending')
            ->pluck('id')
            ->all();

        if (!empty($obsoleteIds)) {
            $this->dues()->whereIn('id', $obsoleteIds)->delete();
        }

        $toInsert = [];

        foreach ($desired as $key => $row) {
            // Strip the disambiguation suffix ('#') added above for duplicate
            // (type, date) pairs — it isn't part of the real matching key.
            $matchKey = rtrim($key, '#');
            $match    = $existing->get($matchKey);

            // Fix for audit C4 — convert to the company's base currency using
            // the plan's own currency and this due's date.
            $conversion = $fx->convert($this->company_id, $baseCurrency, $row['amount'], $currency, $row['due_date']);

            if ($match && $match->status !== PropertyInstallmentDue::STATUS_PENDING) {
                // Already paid, or aged into overdue — historical fact, don't touch.
                $existing->forget($matchKey); // don't let a second desired row match the same paid row
                continue;
            }

            if ($match) {
                $match->update([
                    'amount'        => $row['amount'],
                    'currency'      => $currency,
                    'base_amount'   => $conversion['base_amount'],
                    'base_currency' => $conversion['base_currency'],
                    'fx_rate_used'  => $conversion['fx_rate_used'],
                    'sort_order'    => $order[$key],
                ]);
                $existing->forget($matchKey);
                continue;
            }

            $toInsert[] = [
                'company_id'    => $this->company_id,
                'property_id'   => $this->property_id,
                'plan_id'       => $this->id,
                'due_type'      => $row['due_type'],
                'due_date'      => $row['due_date'],
                'amount'        => $row['amount'],
                'currency'      => $currency,
                'base_amount'   => $conversion['base_amount'],
                'base_currency' => $conversion['base_currency'],
                'fx_rate_used'  => $conversion['fx_rate_used'],
                'status'        => 'pending',
                'paid_date'     => null,
                'notes'         => null,
                'sort_order'    => $order[$key],
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        if (!empty($toInsert)) {
            PropertyInstallmentDue::insert($toInsert);
        }
    }
}