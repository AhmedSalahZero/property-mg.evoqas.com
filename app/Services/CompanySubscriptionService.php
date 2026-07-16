<?php

namespace App\Services;

use App\Models\Company;
use Carbon\CarbonImmutable;

class CompanySubscriptionService
{
    public function calculateEndDate(string $startDate, int $durationMonths): CarbonImmutable
    {
        return CarbonImmutable::parse($startDate, config('app.timezone'))
            ->startOfDay()
            ->addMonthsNoOverflow($durationMonths);
    }

    public function remainingDays(?Company $company): ?int
    {
        if (!$company || !$company->subscription_end_date) {
            return null;
        }

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $endDate = CarbonImmutable::parse($company->subscription_end_date, config('app.timezone'))->startOfDay();

        return $today->diffInDays($endDate, false);
    }

    public function isExpired(?Company $company): bool
    {
        $remainingDays = $this->remainingDays($company);

        return !is_null($remainingDays) && $remainingDays < 0;
    }

    public function isExpiringSoon(?Company $company): bool
    {
        $remainingDays = $this->remainingDays($company);
        $warningDays = (int) config('subscription.warning_days');

        return !is_null($remainingDays) && $remainingDays >= 0 && $remainingDays <= $warningDays;
    }

    public function status(?Company $company): array
    {
        $remainingDays = $this->remainingDays($company);

        return [
            'remaining_days' => $remainingDays,
            'warning_days' => (int) config('subscription.warning_days'),
            'is_expired' => $this->isExpired($company),
            'is_expiring_soon' => $this->isExpiringSoon($company),
            'subscription_end_date' => $company?->subscription_end_date,
        ];
    }
}

