<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyInstallmentPlan;
use App\Models\PropertyInstallmentDue;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyInstallmentController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // INDEX — dedicated full page (replaces the old modal-in-Properties-
    // Index approach for a much friendlier, non-cramped working area).
    // The page itself fetches plan + dues via load() below on mount, the
    // same way every other Property tab (Dashboard, Cash Forecast, ...)
    // renders a shell page and pulls its data from a companion /data
    // endpoint.
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeInstallment($property);

        return Inertia::render('Properties/Installments/Index', [
            'company'  => $company,
            'property' => $property,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // LOAD — return plan + dues (JSON, fetched by the page on mount)
    // ═══════════════════════════════════════════════════════════════════
    public function load(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeInstallment($property);

        $plan = PropertyInstallmentPlan::with('dues')
            ->where('property_id', $property->id)
            ->first();

        return response()->json([
            'plan'     => $plan,
            'dues'     => $plan ? $plan->dues : [],
            'currency' => $property->currency ?? 'EGP',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SAVE — upsert plan + regenerate dues schedule
    // ═══════════════════════════════════════════════════════════════════
    public function save(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeInstallment($property);

        $type = $request->input('installment_type', 'regular');

        // ── Base validation ───────────────────────────────────────────
        $base = $request->validate([
            'installment_type' => 'required|in:regular,variable',
            'currency'         => 'required|string|max:10',
            'delivery_date'    => 'nullable|string|max:7',
            'ready_to_use_date'=> 'nullable|string|max:7',
        ]);

        if ($type === 'regular') {
            $request->validate([
                'signing_amount'           => 'nullable|numeric|min:0',
                'signing_date'             => 'nullable|string|max:7',
                'reservation_amount'       => 'nullable|numeric|min:0',
                'reservation_date'         => 'nullable|string|max:7',

                'installment_rows'                    => 'nullable|array',
                'installment_rows.*.amount'           => 'required|numeric|min:0',
                'installment_rows.*.count'            => 'required|integer|min:1',
                'installment_rows.*.start_date'       => 'required|string|max:7',
                'installment_rows.*.interval'         => 'required|in:monthly,quarterly,semi_annually',

                'has_annual'               => 'boolean',
                'annual_start_date'        => 'nullable|string|max:7',
                'annual_amount'            => 'nullable|numeric|min:0',
                'annual_count'             => 'nullable|integer|min:1',

                'has_delivery'             => 'boolean',
                'delivery_start_date'      => 'nullable|string|max:7',
                'delivery_amount'          => 'nullable|numeric|min:0',
                'delivery_count'           => 'nullable|integer|min:1',
                'delivery_interval'        => 'nullable|in:monthly,quarterly,semi_annually',

                'has_maintenance'          => 'boolean',
                'maintenance_start_date'   => 'nullable|string|max:7',
                'maintenance_amount'       => 'nullable|numeric|min:0',
                'maintenance_count'        => 'nullable|integer|min:1',
                'maintenance_interval'     => 'nullable|in:monthly,quarterly,semi_annually',
            ]);
        }

        if ($type === 'variable') {
            $request->validate([
                'variable_dues'          => 'nullable|array',
                'variable_dues.*.id'     => 'nullable|integer',
                'variable_dues.*.date'   => 'required|date',
                'variable_dues.*.amount' => 'required|numeric|min:0',
                'variable_dues.*.notes'  => 'nullable|string|max:500',
            ]);
        }

        // Fix for audit finding F-2 — the plan upsert plus the due
        // generation/reconciliation that follows it used to run as separate,
        // untransacted steps (and the variable-mode branch's own
        // insert/update/delete sequence had no transaction either). A
        // failure partway through could leave the plan saved with a stale or
        // partial due schedule. Wrapping the whole save in one transaction
        // means the plan and its full due schedule succeed or fail together.
        $plan = DB::transaction(function () use ($request, $company, $property, $type, $base) {

        // ── Upsert plan ───────────────────────────────────────────────
        $plan = PropertyInstallmentPlan::updateOrCreate(
            ['property_id' => $property->id],
            array_merge([
                'company_id'       => $company->id,
                'installment_type' => $type,
                'currency'         => $base['currency'],
                'delivery_date'    => $base['delivery_date'] ?? null,
                'ready_to_use_date'=> $base['ready_to_use_date'] ?? null,
                'created_by'       => auth()->id(),
            ], $type === 'regular' ? [
                'signing_amount'         => $request->input('signing_amount'),
                'signing_date'           => $request->input('signing_date'),
                'reservation_amount'     => $request->input('reservation_amount'),
                'reservation_date'       => $request->input('reservation_date'),
                'installment_rows'       => $request->input('installment_rows', []),
                'has_annual'             => $request->boolean('has_annual'),
                'annual_start_date'      => $request->input('annual_start_date'),
                'annual_amount'          => $request->input('annual_amount'),
                'annual_count'           => $request->input('annual_count'),
                'has_delivery'           => $request->boolean('has_delivery'),
                'delivery_start_date'    => $request->input('delivery_start_date'),
                'delivery_amount'        => $request->input('delivery_amount'),
                'delivery_count'         => $request->input('delivery_count'),
                'delivery_interval'      => $request->input('delivery_interval'),
                'has_maintenance'        => $request->boolean('has_maintenance'),
                'maintenance_start_date' => $request->input('maintenance_start_date'),
                'maintenance_amount'     => $request->input('maintenance_amount'),
                'maintenance_count'      => $request->input('maintenance_count'),
                'maintenance_interval'   => $request->input('maintenance_interval'),
            ] : [])
        );

        // ── Generate schedule ─────────────────────────────────────────
        if ($type === 'regular') {
            $plan->generateDues();
        } else {
            // Variable — reconcile against existing dues instead of a blind
            // delete + insert. This closes the same C2 gap for variable-mode
            // plans that generateDues() closes for regular-mode ones: this
            // branch used to run `$plan->dues()->delete()` unconditionally,
            // wiping every row's 'paid' status the moment the plan was saved
            // again — even just to add one more row.
            //
            // Fix: this used to match existing rows to submitted rows BY
            // DATE (keyed on due_date). That meant editing a row's date —
            // the single most normal edit a user would make — was
            // indistinguishable from deleting the old row and adding a new
            // one: nothing in the submission matched the old date anymore,
            // so a brand new row got inserted for the new date, and the old
            // row only survived because it happened to already be
            // 'overdue' (the "don't delete anything but pending" safety net
            // caught it) — producing a visible duplicate instead of an
            // in-place edit. Matching by the row's own database id (sent
            // back from loadPlan() and round-tripped by the frontend on
            // every submitted row) fixes this at the root: an edited row is
            // now always recognized as the SAME row regardless of what its
            // date changed to, and its status is recomputed from the new
            // date rather than staying stuck on whatever it was before the
            // edit. Rows with no id (genuinely new, added in the form) are
            // still always inserted fresh.
            $currency     = $base['currency'];
            $baseCurrency = strtoupper($company->currency ?: 'EGP');
            $fx           = app(CurrencyConversionService::class);
            $today        = Carbon::today();

            $existingById = $plan->dues()->where('due_type', 'variable')->get()->keyBy('id');

            $keepIds  = [];
            $toInsert = [];

            // A date-only edit should re-derive status the same way the
            // daily MarkOverdueRecords command would, rather than leaving it
            // stuck on whatever it was before the edit (e.g. correcting an
            // 'overdue' row's date into the future must bring it back to
            // 'pending', not leave it permanently overdue).
            $statusForDate = fn (string $date): string =>
                Carbon::parse($date)->lt($today) ? PropertyInstallmentDue::STATUS_OVERDUE : PropertyInstallmentDue::STATUS_PENDING;

            foreach ($request->input('variable_dues', []) as $i => $d) {
                $id    = $d['id'] ?? null;
                $match = $id ? $existingById->get((int) $id) : null;

                $conversion = $fx->convert($company->id, $baseCurrency, (float) $d['amount'], $currency, $d['date']);

                if ($match) {
                    // Same row (matched by id) — apply the edit in place.
                    // A 'paid' row can now be edited too (confirmed product
                    // decision — this is a Property Management tool, not an
                    // accounting ledger, and the frontend already confirms
                    // with the user before submitting a change to a paid
                    // row). Its status is deliberately left as 'paid' rather
                    // than run through $statusForDate() — correcting a typo
                    // in a paid row's date/amount doesn't mean the money
                    // was never actually received.
                    $newStatus = $match->status === PropertyInstallmentDue::STATUS_PAID
                        ? PropertyInstallmentDue::STATUS_PAID
                        : $statusForDate($d['date']);

                    $match->update([
                        'due_date'      => $d['date'],
                        'amount'        => $d['amount'],
                        'currency'      => $currency,
                        'base_amount'   => $conversion['base_amount'],
                        'base_currency' => $conversion['base_currency'],
                        'fx_rate_used'  => $conversion['fx_rate_used'],
                        'notes'         => $d['notes'] ?? null,
                        'status'        => $newStatus,
                        'sort_order'    => $i,
                    ]);
                    $keepIds[] = $match->id;
                    continue;
                }

                $toInsert[] = [
                    'company_id'    => $company->id,
                    'property_id'   => $property->id,
                    'plan_id'       => $plan->id,
                    'due_type'      => 'variable',
                    'due_date'      => $d['date'],
                    'amount'        => $d['amount'],
                    'currency'      => $currency,
                    'base_amount'   => $conversion['base_amount'],
                    'base_currency' => $conversion['base_currency'],
                    'fx_rate_used'  => $conversion['fx_rate_used'],
                    'status'        => $statusForDate($d['date']),
                    'notes'         => $d['notes'] ?? null,
                    'sort_order'    => $i,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            if (!empty($toInsert)) {
                PropertyInstallmentDue::insert($toInsert);
            }

            // Anything left in $existingById that wasn't matched above (i.e.
            // its id isn't in $keepIds) is a row the user removed from the
            // form entirely. Only remove it if it's still 'pending' — a
            // leftover 'overdue' or 'paid' row is history and must survive
            // even if it was removed from the current submission, same
            // protective rule as before this fix (only HOW rows are matched
            // to submitted entries changed — by id now instead of by date —
            // not this deletion safety net).
            $leftoverIds = $existingById->keys()->diff($keepIds)->all();
            if (!empty($leftoverIds)) {
                PropertyInstallmentDue::whereIn('id', $leftoverIds)
                    ->where('status', 'pending')
                    ->delete();
            }
        }

            return $plan;
        });

        $plan->load('dues');

        return response()->json([
            'message' => 'Installment plan saved and schedule generated.',
            'plan'    => $plan,
            'dues'    => $plan->dues,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // MARK PAID
    // ═══════════════════════════════════════════════════════════════════
    public function markPaid(Request $request, Company $company, Property $property, PropertyInstallmentDue $due)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($due->property_id === $property->id, 404);

        $data = $request->validate([
            'paid_date' => 'required|date',
            'notes'     => 'nullable|string|max:500',
        ]);

        $due->update([
            'status'    => PropertyInstallmentDue::STATUS_PAID,
            'paid_date' => $data['paid_date'],
            'notes'     => $data['notes'] ?? $due->notes,
        ]);

        return response()->json(['message' => 'Marked as paid.', 'due' => $due->fresh()]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // MARK UNPAID — undo a Mark Paid, the required first step before a
    // paid row can be deleted (see deleteDue() below).
    // ═══════════════════════════════════════════════════════════════════
    /**
     * Confirmed product decision (July 2026 session): a paid installment
     * due can never be deleted directly — the user must first explicitly
     * un-mark it as paid. This is that "undo" action. It's the mirror
     * image of markPaid(): clears paid_date and reverts status to
     * whatever the automatic daily job (MarkOverdueRecords /
     * PropertyInstallmentDue::autoMarkOverdue()) would have set it to by
     * now if it had never been paid — 'overdue' if due_date has already
     * passed, 'pending' otherwise — so the row lands in the same state a
     * freshly-generated due in that situation would be in, rather than
     * silently going stale as 'pending' forever even if its date is long
     * past.
     *
     * This is NOT an accounting reversal — VERO is a property management
     * tool, not a ledger, so there's no separate "payment" record being
     * deleted here and no audit trail requirement beyond what the
     * property_installment_dues row itself already shows (status +
     * paid_date). Reverting the status is the whole action.
     */
    public function markUnpaid(Company $company, Property $property, PropertyInstallmentDue $due)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($due->property_id === $property->id, 404);

        if ($due->status !== PropertyInstallmentDue::STATUS_PAID) {
            return response()->json(['message' => 'This installment is not currently marked as paid.'], 422);
        }

        $newStatus = Carbon::parse($due->due_date)->lt(Carbon::today())
            ? PropertyInstallmentDue::STATUS_OVERDUE
            : PropertyInstallmentDue::STATUS_PENDING;

        $due->update([
            'status'    => $newStatus,
            'paid_date' => null,
        ]);

        return response()->json(['message' => 'Marked as unpaid.', 'due' => $due->fresh()]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // DELETE SINGLE DUE — explicit, deliberate row deletion
    // ═══════════════════════════════════════════════════════════════════
    /**
     * Hard-deletes exactly one due row — no soft-delete, this is a
     * property management tool rather than an accounting ledger, so
     * there's no requirement to retain a trace of a genuinely-wrong row
     * (e.g. a duplicate) once it's gone.
     *
     * Confirmed product decision (July 2026 session): a row that is
     * currently 'paid' can NEVER be deleted directly — the user must call
     * markUnpaid() first. This used to be enforced only as a stronger
     * confirm() warning on the frontend, which meant a paid row's removal
     * (and the change to paid totals that comes with it) was never
     * actually a deliberate, separate step — the backend simply trusted
     * whatever the frontend sent. It now refuses outright with a 422 if
     * the row is still 'paid', so "un-paying" a row is always its own
     * explicit action before deletion, not a side effect of clicking
     * delete twice fast enough to dismiss a popup.
     *
     * This is deliberately separate from save()'s bulk reconciliation,
     * which never deletes a paid row and only quietly deletes a pending
     * row as a side effect of it disappearing from a resubmitted form —
     * that protects against ACCIDENTAL loss during an unrelated edit, but
     * gave no way to explicitly and intentionally remove one specific row
     * that's genuinely wrong. This endpoint is that explicit action, for
     * any row that isn't (or is no longer) paid.
     */
    public function deleteDue(Company $company, Property $property, PropertyInstallmentDue $due)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        abort_unless($due->property_id === $property->id, 404);

        if ($due->status === PropertyInstallmentDue::STATUS_PAID) {
            return response()->json([
                'message' => 'This installment is marked as paid. Mark it as unpaid first, then delete it.',
            ], 422);
        }

        $due->delete();

        return response()->json(['message' => 'Installment due removed.']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // IMPORT EXCEL (variable type)
    // ═══════════════════════════════════════════════════════════════════
    public function import(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
        $this->authorizeProperty($company, $property);
        $this->authorizeInstallment($property);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $rows = [];
        $data = Excel::toArray([], $request->file('file'));
        $sheet = $data[0] ?? [];

        // Skip header row
        foreach (array_slice($sheet, 1) as $row) {
            $date   = $row[0] ?? null;
            $amount = $row[1] ?? null;
            $notes  = $row[2] ?? null;

            if (empty($date) || empty($amount)) continue;

            // Try to parse the date
            try {
                $parsed = Carbon::parse($date)->toDateString();
            } catch (\Exception $e) {
                continue;
            }

            $rows[] = [
                'date'   => $parsed,
                'amount' => (float)$amount,
                'notes'  => $notes ? (string)$notes : null,
            ];
        }

        return response()->json(['rows' => $rows]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function authorizeInstallment(Property $property): void
    {
        if ($property->ownership !== 'installments') {
            abort(403, 'This property does not have installment ownership.');
        }
    }

    /**
     * Fix for audit finding C-2 — authorizeCompany() alone doesn't confirm
     * {property}/{due} (resolved by Laravel with no company filter) belong
     * to the URL's {company}. See the same fix in PropertyController.
     */
    private function authorizeProperty(Company $company, Property $property): void
    {
        abort_unless($property->company_id === $company->id, 404);
    }
}