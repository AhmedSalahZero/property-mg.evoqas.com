<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CompanyController extends Controller
{
    // ── List companies ───────────────────────────────────────────
    public function index(Request $request)
    {
        abort_unless($request->user()->is_super_admin, 403);
        $subscriptionService = app(CompanySubscriptionService::class);

        // Use get() not paginate() — Index.vue does client-side filtering
        $companies = Company::with('parent')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($subscriptionService) {
                $status = $subscriptionService->status($company);

                return [
                    ...$company->toArray(),
                    ...$status,
                ];
            });

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'warningDays' => (int) config('subscription.warning_days'),
            'displayDaysPerMonth' => (int) config('subscription.display_days_per_month'),
        ]);
    }

    // ── Create form ──────────────────────────────────────────────
    public function create()
    {
        abort_unless(request()->user()->is_super_admin, 403);

        return Inertia::render('Companies/Create', [
            'modules' => Company::MODULES,
            'parents' => Company::where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    // ── Store new company ────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $validated = $this->validateCompany($request);
        $validated = $this->applySubscriptionPayload($validated);

        Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Company created successfully.');
    }

    // ── Show company profile ─────────────────────────────────────
    public function show(Request $request, Company $company)
    {
        $authUser = $request->user();

        abort_unless(
            $authUser->is_super_admin ||
            $authUser->company_id === $company->id,
            403
        );

        return Inertia::render('Companies/Show', [
            'company'    => $company->load(['parent', 'subsidiaries', 'users']),
            'userCount'  => $company->users()->count(),
            'adminCount' => $company->users()->where('role', 'company_admin')->count(),
            'modules'    => Company::MODULES,
        ]);
    }

    // ── Edit form ────────────────────────────────────────────────
    public function edit(Request $request, Company $company)
    {
        abort_unless($request->user()->is_super_admin, 403);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
            'modules' => Company::MODULES,
            'parents' => Company::where('is_active', true)
                ->whereNull('parent_id')
                ->where('id', '!=', $company->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    // ── Update company ───────────────────────────────────────────
    public function update(Request $request, Company $company)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $validated = $this->validateCompany($request, $company->id);
        $validated = $this->applySubscriptionPayload($validated);

        $company->update($validated);

        return redirect()->route('companies.show', $company->id)
            ->with('success', 'Company updated successfully.');
    }

    // ── Delete company ───────────────────────────────────────────
    public function destroy(Request $request, Company $company)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $company->delete(); // SoftDelete

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    // ── Toggle active status ─────────────────────────────────────
    public function toggleActive(Request $request, Company $company)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $company->update(['is_active' => !$company->is_active]);

        return back()->with('success', 'Company status updated.');
    }

    // ── Update module subscriptions ──────────────────────────────
    public function updateModules(Request $request, Company $company)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $request->validate([
            'enabled_modules'   => 'nullable|array',
            'enabled_modules.*' => ['string', Rule::in(array_keys(Company::MODULES))],
        ]);

        $company->update([
            'enabled_modules' => $request->enabled_modules ?? [],
        ]);

        return back()->with('success', 'Module subscriptions updated.');
    }

    // ── Private: shared validation ───────────────────────────────
    private function validateCompany(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                => 'required|string|max:255',
            'trade_name'          => 'nullable|string|max:255',
            'logo'                => 'nullable|image|max:2048',
            'description'         => 'nullable|string',
            'notes'               => 'nullable|string',
            'parent_id'           => 'nullable|exists:companies,id',
            'legal_structure'     => 'nullable|string|max:255',
            'established_date'    => 'nullable|date',
            'established_year'    => 'nullable|integer|min:1900|max:' . date('Y'),
            'registration_number' => 'nullable|string|max:100',
            'tax_id'              => 'nullable|string|max:100',
            'address'             => 'nullable|string|max:500',
            'city'                => 'nullable|string|max:100',
            'country'             => 'nullable|string|max:100',
            'phone'               => 'nullable|string|max:50',
            'email'               => 'nullable|email|max:255',
            'website'             => 'nullable|url|max:255',
            'currency'            => 'nullable|string|max:10',
            'fiscal_year_start'   => ['nullable', Rule::in(['01','02','03','04','05','06','07','08','09','10','11','12'])],
            'tax_type'            => ['nullable', Rule::in(['corporate_income_tax', 'zakat'])],
            'enabled_modules'     => 'nullable|array',
            'enabled_modules.*'   => ['string', Rule::in(array_keys(Company::MODULES))],
            'is_active'           => 'boolean',
            'subscription_start_date' => 'nullable|date|required_with:subscription_duration_months',
            'subscription_duration_months' => [
                'nullable',
                'integer',
                'required_with:subscription_start_date',
                'min:' . (int) config('subscription.min_duration_months'),
                'max:' . (int) config('subscription.max_duration_months'),
            ],
        ]);
    }

    private function applySubscriptionPayload(array $data): array
    {
        $startDate = $data['subscription_start_date'] ?? null;
        $duration = $data['subscription_duration_months'] ?? null;

        if (!$startDate || !$duration) {
            $data['subscription_start_date'] = null;
            $data['subscription_duration_months'] = null;
            $data['subscription_end_date'] = null;

            return $data;
        }

        $service = app(CompanySubscriptionService::class);
        $endDate = $service->calculateEndDate($startDate, (int) $duration);
        $data['subscription_end_date'] = $endDate->toDateString();

        return $data;
    }
}