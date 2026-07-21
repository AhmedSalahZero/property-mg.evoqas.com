<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;

/**
 * Fix for audit finding M-3 — this exact check used to be copy-pasted as a
 * private method independently into 20 different controllers, with no
 * shared base-controller method or middleware tying them together. That's
 * precisely how it was possible for two controllers (CashForecastController
 * and KeepOrSellController — see audit finding C-1) to be missed entirely:
 * there was nothing forcing every new company-scoped controller to remember
 * to paste this in. Centralizing it here means every controller that needs
 * it gets it from one place, and a future controller only has to remember
 * `use AuthorizesCompany;` once instead of re-typing the whole method body.
 *
 * Usage: `use App\Http\Controllers\Concerns\AuthorizesCompany;` at the top
 * of the controller, `use AuthorizesCompany;` inside the class body, then
 * call `$this->authorizeCompany($company);` exactly as before — the method
 * name and behavior are unchanged, so no call site anywhere in the app
 * needed to change.
 */
trait AuthorizesCompany
{
    /**
     * Confirms the logged-in user actually belongs to $company (or is a
     * super-admin, who belongs to every company). Aborts with 403 if not.
     *
     * This checks ONLY that the top-level {company} in the URL belongs to
     * the user — it does NOT verify that a more deeply nested resource
     * ({property}, {expense}, {contract}, etc.) belongs to that same
     * company. See audit finding C-2 and each controller's own
     * authorizeProperty()/authorizeExpense()/authorizeContract()-style
     * helpers for that second, separate check.
     */
    private function authorizeCompany(Company $company): void
    {
        $user = auth()->user();
        if (! $user->is_super_admin && $user->company_id !== $company->id) {
            abort(403);
        }
    }
}
