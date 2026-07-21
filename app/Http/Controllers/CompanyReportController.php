<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CustomReport;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class CompanyReportController extends Controller
{
    use AuthorizesCompany;

    // ── Report catalogue page ────────────────────────────────────────────────
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $savedReports = CustomReport::where('company_id', $company->id)
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'data_source', 'last_run_at', 'created_by', 'created_at', 'updated_at']);

        return Inertia::render('Reports/Index', [
            'company'      => $company,
            'savedReports' => $savedReports,
        ]);
    }
}
