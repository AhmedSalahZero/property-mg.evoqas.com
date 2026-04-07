<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyExpensePayment;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertyExpenseController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $expenses = PropertyExpense::where('company_id', $company->id)
            ->where('property_id', $property->id)
            ->with([
                'expenseCategory:id,category_name',
                'expenseItem:id,item_name',
                'payments',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($e) {
                return [
                    'id'                  => $e->id,
                    'expense_category'    => $e->expenseCategory?->category_name,
                    'expense_item'        => $e->expenseItem?->item_name,
                    'expense_date'        => $e->expense_date->format('Y-m-d'),
                    'expense_amount'      => (float) $e->expense_amount,
                    'currency'            => $e->currency,
                    'fx_rate'             => $e->fx_rate ? (float) $e->fx_rate : null,
                    'notes'               => $e->notes,
                    'status'              => $e->status,
                    'total_paid'          => $e->totalPaid(),
                    'balance'             => $e->balance(),
                    'payments'            => $e->payments->map(fn($p) => [
                        'id'           => $p->id,
                        'payment_date' => $p->payment_date->format('Y-m-d'),
                        'amount'       => (float) $p->amount,
                    ])->values()->all(),
                ];
            });

        // Load expense categories with their items for the form selectors
        $expenseCategories = ExpenseCategory::where('company_id', $company->id)
            ->with(['items' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'category_name']);

        return Inertia::render('Properties/Expenses/Index', [
            'company'           => $company,
            'property'          => $property->load([
                'propertyCategory:id,category_name',
                'propertyType:id,type_name',
            ]),
            'expenses'          => $expenses,
            'expenseCategories' => $expenseCategories,
            'currencyOptions'   => $this->currencyOptions(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company, Property $property)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'expense_category_id'       => 'required|exists:expense_categories,id',
            'expense_item_id'           => 'required|exists:expense_items,id',
            'expense_date'              => 'required|date',
            'expense_amount'            => 'required|numeric|min:0.01',
            'currency'                  => 'required|string|max:10',
            'fx_rate'                   => 'nullable|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
            'payments'                  => 'nullable|array',
            'payments.*.payment_date'   => 'required|date',
            'payments.*.amount'         => 'required|numeric|min:0.01',
        ]);

        $expense = PropertyExpense::create([
            'company_id'          => $company->id,
            'property_id'         => $property->id,
            'expense_category_id' => $data['expense_category_id'],
            'expense_item_id'     => $data['expense_item_id'],
            'expense_date'        => $data['expense_date'],
            'expense_amount'      => $data['expense_amount'],
            'currency'            => $data['currency'],
            'fx_rate'             => $data['fx_rate'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'status'              => 'unpaid',
            'created_by'          => auth()->id(),
        ]);

        foreach ($data['payments'] ?? [] as $p) {
            PropertyExpensePayment::create([
                'company_id'          => $company->id,
                'property_expense_id' => $expense->id,
                'payment_date'        => $p['payment_date'],
                'amount'              => $p['amount'],
            ]);
        }

        $expense->recalculateStatus();

        return back()->with('success', 'Expense added successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════════
    public function update(Request $request, Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'expense_category_id'       => 'required|exists:expense_categories,id',
            'expense_item_id'           => 'required|exists:expense_items,id',
            'expense_date'              => 'required|date',
            'expense_amount'            => 'required|numeric|min:0.01',
            'currency'                  => 'required|string|max:10',
            'fx_rate'                   => 'nullable|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        $expense->update([
            'expense_category_id' => $data['expense_category_id'],
            'expense_item_id'     => $data['expense_item_id'],
            'expense_date'        => $data['expense_date'],
            'expense_amount'      => $data['expense_amount'],
            'currency'            => $data['currency'],
            'fx_rate'             => $data['fx_rate'] ?? null,
            'notes'               => $data['notes'] ?? null,
        ]);

        $expense->recalculateStatus();

        return back()->with('success', 'Expense updated successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);
        $expense->payments()->delete();
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADD PAYMENT
    // ═══════════════════════════════════════════════════════════════════
    public function addPayment(Request $request, Company $company, Property $property, PropertyExpense $expense)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'payments'                => 'required|array|min:1',
            'payments.*.payment_date' => 'required|date',
            'payments.*.amount'       => 'required|numeric|min:0.01',
        ]);

        foreach ($data['payments'] as $p) {
            PropertyExpensePayment::create([
                'company_id'          => $company->id,
                'property_expense_id' => $expense->id,
                'payment_date'        => $p['payment_date'],
                'amount'              => $p['amount'],
            ]);
        }

        $expense->recalculateStatus();

        return back()->with('success', 'Payment recorded successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // DELETE PAYMENT
    // ═══════════════════════════════════════════════════════════════════
    public function deletePayment(Company $company, Property $property, PropertyExpense $expense, PropertyExpensePayment $payment)
    {
        $this->authorizeCompany($company);
        $payment->delete();
        $expense->recalculateStatus();
        return back()->with('success', 'Payment removed.');
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

    private function currencyOptions(): array
    {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED'];
    }
}