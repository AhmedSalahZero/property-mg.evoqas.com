<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyInstallmentPlan;
use App\Models\PropertyInstallmentDue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PropertyInstallmentController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // LOAD — return plan + dues for the modal
    // ═══════════════════════════════════════════════════════════════════
    public function load(Company $company, Property $property)
    {
        $this->authorizeCompany($company);
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
                'variable_dues.*.date'   => 'required|date',
                'variable_dues.*.amount' => 'required|numeric|min:0',
                'variable_dues.*.notes'  => 'nullable|string|max:500',
            ]);
        }

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
            // Variable — save dues rows directly
            $plan->dues()->delete();
            $currency = $base['currency'];
            $rows     = [];
            foreach ($request->input('variable_dues', []) as $i => $d) {
                $rows[] = [
                    'company_id'  => $company->id,
                    'property_id' => $property->id,
                    'plan_id'     => $plan->id,
                    'due_type'    => 'variable',
                    'due_date'    => $d['date'],
                    'amount'      => $d['amount'],
                    'currency'    => $currency,
                    'status'      => 'pending',
                    'notes'       => $d['notes'] ?? null,
                    'sort_order'  => $i,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
            if (!empty($rows)) {
                PropertyInstallmentDue::insert($rows);
            }
        }

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
    // IMPORT EXCEL (variable type)
    // ═══════════════════════════════════════════════════════════════════
    public function import(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);
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
    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }

    private function authorizeInstallment(Property $property): void
    {
        if ($property->ownership !== 'installments') {
            abort(403, 'This property does not have installment ownership.');
        }
    }
}