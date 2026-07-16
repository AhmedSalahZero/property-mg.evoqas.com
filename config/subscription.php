<?php

return [
    'warning_days' => (int) env('SUBSCRIPTION_WARNING_DAYS', 30),
    'display_days_per_month' => (int) env('SUBSCRIPTION_DISPLAY_DAYS_PER_MONTH', 30),
    'min_duration_months' => (int) env('SUBSCRIPTION_MIN_DURATION_MONTHS', 1),
    'max_duration_months' => (int) env('SUBSCRIPTION_MAX_DURATION_MONTHS', 120),
    'expired_message' => env('SUBSCRIPTION_EXPIRED_MESSAGE', 'Subscription expired. Please contact support.'),
];

