<?php

namespace Tests\Unit;

use App\Http\Controllers\KeepOrSellController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Fix for audit finding F-3.
 *
 * computeIRR() and runComputation() are private methods on the controller,
 * by design (they're implementation details, not an HTTP-facing surface).
 * ReflectionMethod::invoke() is the standard, safe way to unit-test a
 * private method directly without changing its visibility or restructuring
 * the controller just to make it "testable" — nothing about the production
 * code changes to support these tests.
 *
 * Both methods are pure math with no database or HTTP dependency, so — same
 * reasoning as RentContractScheduleTest — a plain PHPUnit\Framework\TestCase
 * is enough; no app/database bootstrap is needed to exercise them.
 *
 * Drop this file in tests/Unit/ and run: php artisan test --filter=KeepOrSellComputationTest
 */
class KeepOrSellComputationTest extends TestCase
{
    private function callComputeIRR(array $annualCFs, float $terminalValue, int $holdingYears): ?float
    {
        $method = new ReflectionMethod(KeepOrSellController::class, 'computeIRR');
        $method->setAccessible(true);

        return $method->invoke(new KeepOrSellController(), $annualCFs, $terminalValue, $holdingYears);
    }

    private function callRunComputation(array $data): array
    {
        $method = new ReflectionMethod(KeepOrSellController::class, 'runComputation');
        $method->setAccessible(true);

        return $method->invoke(new KeepOrSellController(), $data);
    }

    // ── IRR — analytically verifiable cases ─────────────────────────────

    public function test_irr_matches_the_analytical_answer_for_a_simple_two_year_cash_flow(): void
    {
        // Year 1: -100 outflow. Year 2: 0 operating CF, but a 121 terminal
        // value lands at year 2. NPV(r) = -100/(1+r) + 121/(1+r)^2.
        // Solving NPV(r)=0 by hand: 1+r = 121/100 = 1.21 → r = 0.21 → 21%.
        // This is the textbook check for any IRR bisection implementation.
        $annualCFs = [
            ['year' => 1, 'net_cf' => -100.0],
            ['year' => 2, 'net_cf' => 0.0],
        ];

        $irr = $this->callComputeIRR($annualCFs, 121.0, 2);

        $this->assertNotNull($irr);
        $this->assertEqualsWithDelta(21.0, $irr, 0.01);
    }

    public function test_irr_is_null_when_every_cash_flow_is_positive(): void
    {
        // With no sign change anywhere in the cash flow stream (every net
        // CF positive, terminal value positive), NPV(r) is positive for
        // every r in the search range and never crosses zero — there is no
        // real IRR, and the method must say so (null) rather than return a
        // meaningless number from wherever the bisection loop happened to
        // stop.
        $annualCFs = [
            ['year' => 1, 'net_cf' => 100.0],
            ['year' => 2, 'net_cf' => 100.0],
        ];

        $irr = $this->callComputeIRR($annualCFs, 500.0, 2);

        $this->assertNull($irr);
    }

    public function test_irr_is_null_when_every_cash_flow_is_negative(): void
    {
        $annualCFs = [
            ['year' => 1, 'net_cf' => -100.0],
        ];

        $irr = $this->callComputeIRR($annualCFs, -50.0, 1);

        $this->assertNull($irr);
    }

    // ── Annual Cash Flow Engine ──────────────────────────────────────────

    public function test_first_year_uses_contracted_revenue_when_available(): void
    {
        $currentYear = (int) date('Y');

        $result = $this->callRunComputation([
            'market_value'            => 1000000,
            'selling_costs_pct'       => 5,
            'holding_years'           => 1,
            'rent_growth_rate_pct'    => 10,
            'other_opex_pct'          => 0,
            'corporate_tax_rate_pct'  => 0,
            'discount_rate_pct'       => 12,
            'exit_value_method'       => 'appreciation',
            'appreciation_rate_pct'   => 0,
            'exit_cap_rate_pct'       => 0,
            'contracted_revenues'     => [$currentYear => 120000],
            'contracted_expenses'     => [],
            'installment_by_year'     => [],
            'last_contracted_rent'    => 5000, // deliberately different from the contracted figure above
        ]);

        // The contracted figure must win outright — the growth-rate
        // projection (from last_contracted_rent) must NOT be blended in for
        // a year that already has real contracted data.
        $this->assertSame(120000.0, $result['annual_cashflows'][0]['gross_revenue']);
    }

    public function test_net_sale_proceeds_deducts_selling_costs_from_market_value(): void
    {
        $result = $this->callRunComputation([
            'market_value'            => 1000000,
            'selling_costs_pct'       => 5, // 5%
            'holding_years'           => 1,
            'rent_growth_rate_pct'    => 0,
            'other_opex_pct'          => 0,
            'corporate_tax_rate_pct'  => 0,
            'discount_rate_pct'       => 10,
            'exit_value_method'       => 'appreciation',
            'appreciation_rate_pct'   => 0,
            'exit_cap_rate_pct'       => 0,
            'contracted_revenues'     => [],
            'contracted_expenses'     => [],
            'installment_by_year'     => [],
            'last_contracted_rent'    => 0,
        ]);

        $this->assertSame(950000.0, $result['net_sale_proceeds']);
    }

    public function test_corporate_tax_is_never_charged_on_a_loss_making_year(): void
    {
        $currentYear = (int) date('Y');

        $result = $this->callRunComputation([
            'market_value'            => 1000000,
            'selling_costs_pct'       => 5,
            'holding_years'           => 1,
            'rent_growth_rate_pct'    => 0,
            'other_opex_pct'          => 0,
            'corporate_tax_rate_pct'  => 25, // 25% — would be charged if the (buggy) logic ignored the loss
            'discount_rate_pct'       => 10,
            'exit_value_method'       => 'appreciation',
            'appreciation_rate_pct'   => 0,
            'exit_cap_rate_pct'       => 0,
            'contracted_revenues'     => [$currentYear => 10000],
            'contracted_expenses'     => [$currentYear => 50000], // expenses exceed revenue → a loss
            'installment_by_year'     => [],
            'last_contracted_rent'    => 0,
        ]);

        $this->assertSame(0.0, $result['annual_cashflows'][0]['corporate_tax']);
    }
}
