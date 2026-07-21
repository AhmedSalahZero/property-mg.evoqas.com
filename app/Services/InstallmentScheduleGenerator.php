<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Pure, stateless "Regular Mode" installment schedule generator.
 *
 * Extracted (July 2026) from PropertyInstallmentPlan::generateDuesBody() so
 * the Investment Decision Tool's Seller/Developer Installments funding path
 * can generate the exact same due dates/amounts a real installment plan
 * would — without duplicating the logic and risking the two drifting apart
 * over time. PropertyInstallmentPlan now calls generateRows() too (see that
 * class); this file has zero Eloquent/DB dependency, so it's equally usable
 * against a real owned property OR a not-yet-purchased prospect.
 *
 * generateRows() intentionally returns ONLY the generated rows — no
 * database reconciliation, no persistence. PropertyInstallmentPlan still
 * owns the "match against existing dues, protect paid rows" logic itself,
 * since that's specific to a live installment plan; this class only ever
 * answers "given these plan fields, what dates and amounts fall out of
 * them" — the one piece of logic that genuinely needs to be identical in
 * both places.
 */
class InstallmentScheduleGenerator
{
    /**
     * @param  array  $plan  Same field shape as PropertyInstallmentPlan's
     *         regular-mode fillable columns: signing_amount, signing_date,
     *         reservation_amount, reservation_date, installment_rows
     *         (array of {amount, count, start_date, interval}), has_annual,
     *         annual_start_date, annual_amount, annual_count, has_delivery,
     *         delivery_start_date, delivery_amount, delivery_count,
     *         delivery_interval, has_maintenance, maintenance_start_date,
     *         maintenance_amount, maintenance_count, maintenance_interval.
     *         Dates are "MM/YYYY" strings, same convention as everywhere
     *         else in this app.
     *
     * @return array<int, array{due_type: string, due_date: string, amount: float}>
     *         due_date is a Y-m-d string (first of the month). Rows are in
     *         generation order (signing, reservation, installment rows in
     *         repeater order, annual, delivery, maintenance) — the same
     *         order PropertyInstallmentPlan's $order tracking produces.
     */
    public static function generateRows(array $plan): array
    {
        $rows = [];

        $add = function (string $type, string $monthYear, float $amount) use (&$rows) {
            $rows[] = [
                'due_type' => $type,
                'due_date' => self::parseMonthYear($monthYear)->startOfMonth()->toDateString(),
                'amount'   => $amount,
            ];
        };

        // ── Signing ──────────────────────────────────────────────────
        if (!empty($plan['signing_amount']) && (float) $plan['signing_amount'] > 0 && !empty($plan['signing_date'])) {
            $add('signing', $plan['signing_date'], (float) $plan['signing_amount']);
        }

        // ── Reservation ──────────────────────────────────────────────
        if (!empty($plan['reservation_amount']) && (float) $plan['reservation_amount'] > 0 && !empty($plan['reservation_date'])) {
            $add('reservation', $plan['reservation_date'], (float) $plan['reservation_amount']);
        }

        // ── Installment rows (repeater) ─────────────────────────────
        foreach ($plan['installment_rows'] ?? [] as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $count  = (int) ($row['count'] ?? 0);
            if ($amount <= 0 || $count <= 0 || empty($row['start_date'])) {
                continue;
            }

            $months  = self::intervalMonths($row['interval'] ?? 'monthly');
            $current = self::parseMonthYear($row['start_date']);

            for ($i = 0; $i < $count; $i++) {
                $add('installment', $current->format('m/Y'), $amount);
                $current->addMonths($months);
            }
        }

        // ── Annual ───────────────────────────────────────────────────
        if (!empty($plan['has_annual'])
            && !empty($plan['annual_amount']) && (float) $plan['annual_amount'] > 0
            && !empty($plan['annual_start_date'])
            && (int) ($plan['annual_count'] ?? 0) > 0
        ) {
            $current = self::parseMonthYear($plan['annual_start_date']);
            for ($i = 0; $i < (int) $plan['annual_count']; $i++) {
                $add('annual', $current->format('m/Y'), (float) $plan['annual_amount']);
                $current->addYear();
            }
        }

        // ── Delivery ─────────────────────────────────────────────────
        if (!empty($plan['has_delivery'])
            && !empty($plan['delivery_amount']) && (float) $plan['delivery_amount'] > 0
            && !empty($plan['delivery_start_date'])
            && (int) ($plan['delivery_count'] ?? 0) > 0
        ) {
            $months  = self::intervalMonths($plan['delivery_interval'] ?? 'monthly');
            $current = self::parseMonthYear($plan['delivery_start_date']);
            for ($i = 0; $i < (int) $plan['delivery_count']; $i++) {
                $add('delivery', $current->format('m/Y'), (float) $plan['delivery_amount']);
                $current->addMonths($months);
            }
        }

        // ── Maintenance ──────────────────────────────────────────────
        if (!empty($plan['has_maintenance'])
            && !empty($plan['maintenance_amount']) && (float) $plan['maintenance_amount'] > 0
            && !empty($plan['maintenance_start_date'])
            && (int) ($plan['maintenance_count'] ?? 0) > 0
        ) {
            $months  = self::intervalMonths($plan['maintenance_interval'] ?? 'monthly');
            $current = self::parseMonthYear($plan['maintenance_start_date']);
            for ($i = 0; $i < (int) $plan['maintenance_count']; $i++) {
                $add('maintenance', $current->format('m/Y'), (float) $plan['maintenance_amount']);
                $current->addMonths($months);
            }
        }

        return $rows;
    }

    public static function parseMonthYear(string $value): Carbon
    {
        // Accepts MM/YYYY or YYYY-MM — identical to
        // PropertyInstallmentPlan::parseMonthYear().
        if (str_contains($value, '/')) {
            [$m, $y] = explode('/', $value);
            return Carbon::createFromDate((int) $y, (int) $m, 1);
        }
        return Carbon::parse($value . '-01');
    }

    public static function intervalMonths(string $interval): int
    {
        return match ($interval) {
            'quarterly'     => 3,
            'semi_annually' => 6,
            default         => 1, // monthly
        };
    }
}
