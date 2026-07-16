<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanySubscriptionService;
use Illuminate\Http\Request;

class CompanySubscriptionStatusController extends Controller
{
    public function __invoke(Request $request, CompanySubscriptionService $subscriptionService)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->is_super_admin) {
            return response()->json([
                'is_super_admin' => true,
                'remaining_days' => null,
                'warning_days' => (int) config('subscription.warning_days'),
                'is_expired' => false,
                'is_expiring_soon' => false,
                'subscription_end_date' => null,
                'message' => null,
            ]);
        }

        $company = Company::find($user->company_id);
        $status = $subscriptionService->status($company);

        return response()->json([
            'is_super_admin' => false,
            ...$status,
            'message' => $status['is_expired']
                ? (string) config('subscription.expired_message')
                : null,
        ]);
    }
}

