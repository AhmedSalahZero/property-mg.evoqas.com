<?php

namespace Tests\Unit;

use App\Models\RentContract;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Fix for audit finding F-3.
 *
 * These tests protect the calculation engine behind rent contracts:
 * rent-basis selection, the min-rent floor, management-fee revenue
 * conversion, and — the part most likely to silently break in a future
 * refactor — annual increase compounding across both the legacy single-rate
 * field and the newer year-by-year schedule.
 *
 * Extends the app's own Tests\TestCase (Laravel's testing base class), NOT
 * plain PHPUnit\Framework\TestCase. Earlier draft of this file used plain
 * PHPUnit directly on the reasoning that these methods are pure calculation
 * with no database involved — true for the calculations themselves, but
 * RentContract casts start_date/end_date as 'date', and simply assigning
 * that attribute (even on a `new RentContract([...])` that's never saved)
 * makes Eloquent look up the connection's date format via
 * $this->getConnection() — which only exists once Laravel's service
 * container has booted. Plain PHPUnit never boots the framework, so that
 * connection resolver is null and every test failed with "Call to a member
 * function connection() on null" before a single assertion ever ran.
 * Extending Tests\TestCase boots the app once so that lookup has something
 * to call. No database connection is actually opened and no migration is
 * required — this class never runs a query, it only needs the framework's
 * connection resolver to exist. This makes the tests slightly slower to
 * boot than plain PHPUnit would be, but still fast, and safe to run without
 * any special test-database setup.
 *
 * Drop this file in tests/Unit/ and run: php artisan test --filter=RentContractScheduleTest
 * (or vendor/bin/phpunit tests/Unit/RentContractScheduleTest.php)
 */
class RentContractScheduleTest extends TestCase
{
    private function makeContract(array $overrides = []): RentContract
    {
        return new RentContract(array_merge([
            'monthly_rent_amount'      => 10000,
            'min_monthly_rent'         => null,
            'revenue_type'             => RentContract::REVENUE_DIRECT_RENT,
            'management_fee_rate'      => null,
            'start_date'               => '2026-01-15',
            'annual_increase_rate'     => 0,
            'annual_increase_schedule' => [],
        ], $overrides));
    }

    // ── Rent Basis Selection ────────────────────────────────────────────

    public function test_rent_basis_uses_contracted_amount_when_no_minimum_is_set(): void
    {
        $contract = $this->makeContract(['monthly_rent_amount' => 10000, 'min_monthly_rent' => null]);
        $this->assertSame(10000.0, $contract->rentBasis());
    }

    public function test_rent_basis_uses_minimum_floor_when_it_is_set_and_positive(): void
    {
        $contract = $this->makeContract(['monthly_rent_amount' => 10000, 'min_monthly_rent' => 12000]);
        $this->assertSame(12000.0, $contract->rentBasis());
    }

    public function test_rent_basis_ignores_a_zero_minimum(): void
    {
        // Zero is stored as "not set" for this field, not a real floor of zero.
        $contract = $this->makeContract(['monthly_rent_amount' => 10000, 'min_monthly_rent' => 0]);
        $this->assertSame(10000.0, $contract->rentBasis());
    }

    // ── Revenue Amount (Direct Rent vs Management Fee) ──────────────────

    public function test_revenue_amount_equals_monthly_rent_for_direct_rent(): void
    {
        $contract = $this->makeContract(['revenue_type' => RentContract::REVENUE_DIRECT_RENT]);
        $this->assertSame(10000.0, $contract->revenueAmount(10000.0));
    }

    public function test_revenue_amount_is_fee_percentage_for_management_fee_contracts(): void
    {
        $contract = $this->makeContract([
            'revenue_type'        => RentContract::REVENUE_MANAGEMENT_FEE,
            'management_fee_rate' => 10, // 10%
        ]);
        $this->assertSame(1000.0, $contract->revenueAmount(10000.0));
    }

    // ── Annual Increase Compounding — legacy single-rate field ──────────

    public function test_legacy_single_rate_no_increase_before_the_first_anniversary_boundary(): void
    {
        // start_date 2026-01-15 → increase boundary is 2027-01-16.
        // Any date before that must still charge the original basis.
        $contract = $this->makeContract([
            'monthly_rent_amount'  => 10000,
            'annual_increase_rate' => 10, // 10%
        ]);

        $this->assertSame(10000.0, $contract->rentBasisForDate(Carbon::parse('2026-06-01')));
        $this->assertSame(10000.0, $contract->rentBasisForDate(Carbon::parse('2027-01-15'))); // one day before boundary
    }

    public function test_legacy_single_rate_applies_on_and_after_the_anniversary_boundary(): void
    {
        $contract = $this->makeContract([
            'monthly_rent_amount'  => 10000,
            'annual_increase_rate' => 10, // 10%
        ]);

        // On the boundary itself and any time in year 2: +10% once.
        $this->assertSame(11000.0, $contract->rentBasisForDate(Carbon::parse('2027-01-16')));
        $this->assertSame(11000.0, $contract->rentBasisForDate(Carbon::parse('2027-12-01')));
    }

    public function test_legacy_single_rate_compounds_again_at_the_second_anniversary(): void
    {
        $contract = $this->makeContract([
            'monthly_rent_amount'  => 10000,
            'annual_increase_rate' => 10, // 10%
        ]);

        // Year 3 (from 2028-01-16): 10000 * 1.10 * 1.10 = 12100, compounded
        // on the PREVIOUS year's rent, not the original basis each time.
        $this->assertSame(12100.0, $contract->rentBasisForDate(Carbon::parse('2028-02-01')));
    }

    public function test_zero_legacy_rate_never_increases_the_rent(): void
    {
        $contract = $this->makeContract([
            'monthly_rent_amount'  => 10000,
            'annual_increase_rate' => 0,
        ]);

        $this->assertSame(10000.0, $contract->rentBasisForDate(Carbon::parse('2030-01-01')));
    }

    // ── Annual Increase Compounding — full year-by-year schedule ────────

    public function test_schedule_based_increases_apply_at_each_years_own_rate(): void
    {
        // start_date 2026-01-15. Schedule: year-2 boundary +5%, year-3 boundary +8%.
        // Rows are keyed by an internal ordinal "year" position in the
        // schedule (matching how normalizeIncreaseSchedule() stores/sorts
        // them), not by calendar year — each successive boundary consumes
        // the next row in order.
        $contract = $this->makeContract([
            'monthly_rent_amount'      => 10000,
            'annual_increase_rate'     => 0,
            'annual_increase_schedule' => [
                ['year' => 2027, 'rate' => 5],
                ['year' => 2028, 'rate' => 8],
            ],
        ]);

        // Before the first boundary: still the original basis.
        $this->assertSame(10000.0, $contract->rentBasisForDate(Carbon::parse('2026-12-01')));

        // First boundary (2027-01-16 onward): 10000 * 1.05 = 10500.
        $this->assertSame(10500.0, $contract->rentBasisForDate(Carbon::parse('2027-06-01')));

        // Second boundary (2028-01-16 onward): 10500 * 1.08 = 11340
        // (compounds on the already-increased amount, not the original basis).
        $this->assertSame(11340.0, $contract->rentBasisForDate(Carbon::parse('2028-06-01')));
    }

    public function test_schedule_takes_priority_over_the_legacy_rate_when_both_are_present(): void
    {
        // If a schedule exists at all, rentBasisForDate() must use it —
        // never silently fall back to the legacy scalar rate alongside it.
        $contract = $this->makeContract([
            'monthly_rent_amount'      => 10000,
            'annual_increase_rate'     => 50, // would give a very different answer if used
            'annual_increase_schedule' => [
                ['year' => 2027, 'rate' => 5],
            ],
        ]);

        $this->assertSame(10500.0, $contract->rentBasisForDate(Carbon::parse('2027-06-01')));
    }

    // ── Insurance-style min-rent interaction with increases ─────────────

    public function test_increase_compounds_on_the_min_rent_floor_when_it_is_the_active_basis(): void
    {
        $contract = $this->makeContract([
            'monthly_rent_amount'  => 8000,
            'min_monthly_rent'     => 10000, // this is the active basis, not 8000
            'annual_increase_rate'=> 10,
        ]);

        $this->assertSame(11000.0, $contract->rentBasisForDate(Carbon::parse('2027-06-01')));
    }
}