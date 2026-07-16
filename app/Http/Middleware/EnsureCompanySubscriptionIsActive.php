<?php

namespace App\Http\Middleware;

use App\Services\CompanySubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySubscriptionIsActive
{
    public function __construct(private readonly CompanySubscriptionService $subscriptionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->is_super_admin) {
            return $next($request);
        }

        $company = $user->company;
        if (!$company) {
            return $next($request);
        }

        if (!$this->subscriptionService->isExpired($company)) {
            return $next($request);
        }

        $message = (string) config('subscription.expired_message');

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}

