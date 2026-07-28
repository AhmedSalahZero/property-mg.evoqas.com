<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Shared logic behind the "Payment Schedule" repeater on Direct (Property)
 * and Corporate expenses. Both expense models are identically shaped for
 * this purpose (see PropertyExpense::paymentSchedule() / totalPaid() and
 * CorporateExpense's identical pair), so this service works generically
 * against either — no per-expense-type branching anywhere in here.
 *
 * Two responsibilities:
 *   1. validateAndBuildRows() — turn raw repeater input into clean rows,
 *      rejecting anything that doesn't sum to exactly 100%.
 *   2. outstandingRows() — the actual forecasting math: given a paid total
 *      and a set of schedule rows ordered oldest-first, work out how much
 *      of each row is still outstanding after applying payments in date
 *      order. This is what Cash Forecast reads instead of falling back to
 *      the old expense_date-based guess, whenever a schedule exists.
 *
 * Schedule rows never carry their own "paid" status — they're always just
 * the PLAN. Actual cash paid lives entirely in the expense's own
 * payments()/totalPaid(), completely untouched by anything in here. That's
 * what makes replaceSchedule() safe to just delete-and-recreate on every
 * save: there's no payment history sitting on these rows that a wholesale
 * replace could destroy, unlike rent collections or installment dues.
 */
class ExpensePaymentScheduleService
{
    /**
     * Validate raw repeater rows against the expense's total amount and
     * return clean rows ready to insert, each with its 'amount' computed
     * from percentage. Throws InvalidArgumentException with a
     * user-presentable message on any validation failure — callers should
     * catch this and turn it into a 422/validation error.
     *
     * @param array  $rawRows       Each: ['percentage' => float, 'forecasted_date' => string|null, 'payment_term' => string|null]
     * @param float  $expenseAmount The expense's total committed amount
     * @param Carbon $expenseDate   The expense's own expense_date — the anchor a payment_term counts forward from
     */
    public function validateAndBuildRows(array $rawRows, float $expenseAmount, Carbon $expenseDate): array
    {
        if (empty($rawRows)) {
            throw new InvalidArgumentException('At least one payment schedule row is required.');
        }

        $clean = [];
        $percentageTotal = 0.0;

        foreach ($rawRows as $i => $row) {
            $percentage = round((float) ($row['percentage'] ?? 0), 2);

            if ($percentage <= 0) {
                throw new InvalidArgumentException("Row " . ($i + 1) . ": percentage must be greater than 0.");
            }

            $term = $row['payment_term'] ?? null;
            $date = null;

            if (!empty($row['forecasted_date'])) {
                // Manually picked (or already-computed-from-a-term-then-edited) date wins.
                $date = Carbon::parse($row['forecasted_date']);
            } elseif ($term) {
                $date = $this->dateForTerm($term, $expenseDate);
            } else {
                throw new InvalidArgumentException("Row " . ($i + 1) . ": a forecasted date or payment term is required.");
            }

            $clean[] = [
                'percentage'      => $percentage,
                'amount'          => round($expenseAmount * $percentage / 100, 2),
                'forecasted_date' => $date->toDateString(),
                'payment_term'    => $term,
                'sort_order'      => $i,
            ];

            $percentageTotal += $percentage;
        }

        // Rounding tolerance: percentages are stored to 2dp, so a genuine
        // 3-way split (e.g. 33.33/33.33/33.34) can land at 100.00 exactly if
        // entered carefully, but floating point addition of several 2dp
        // values can drift by a fraction of a cent. 0.01 is intentionally
        // tight — enough to absorb that drift, not enough to let a real
        // user mistake (e.g. forgetting a row, entering 90% total) through.
        if (abs($percentageTotal - 100.0) > 0.01) {
            throw new InvalidArgumentException(
                "Payment schedule percentages must total 100%. Currently: " . round($percentageTotal, 2) . "%."
            );
        }

        return $clean;
    }

    /**
     * Built-in payment terms — counted forward from the expense's own
     * expense_date.
     *
     * Fix: "Net 30" in standard business usage means "due in 1 calendar
     * month" (e.g. an expense dated Jan 31 is due Feb 28, not Mar 2) — not
     * literally 30 days later. Same for Net 60/90/120/150/180, which are
     * all clean month multiples (2/3/4/5/6 months). Net 45 and Net 75 have
     * no clean calendar-month equivalent ("a month and a half" isn't a
     * real unit), so those two stay literal-day terms, exactly as before.
     * 'cash' means due immediately — 0 days, i.e. the expense_date itself.
     */
    public function dateForTerm(string $term, Carbon $anchorDate): Carbon
    {
        $termMonths = [
            'net_30'  => 1,
            'net_60'  => 2,
            'net_90'  => 3,
            'net_120' => 4,
            'net_150' => 5,
            'net_180' => 6,
        ];

        $termDays = [
            'cash'   => 0,
            'net_45' => 45,
            'net_75' => 75,
        ];

        if (array_key_exists($term, $termMonths)) {
            // addMonthsNoOverflow clamps to the last day of the target
            // month instead of rolling into the next one (e.g. Jan 31 + 1
            // month = Feb 28, not Mar 3).
            return $anchorDate->copy()->addMonthsNoOverflow($termMonths[$term]);
        }

        if (array_key_exists($term, $termDays)) {
            return $anchorDate->copy()->addDays($termDays[$term]);
        }

        throw new InvalidArgumentException("Unknown payment term: {$term}");
    }

    /**
     * Replace an expense's entire payment schedule with a fresh set of
     * rows. Safe to call as a wholesale delete-and-recreate on every save —
     * see class docblock for why (schedule rows never hold payment history
     * of their own).
     *
     * $expense must expose a paymentSchedule() HasMany relation (both
     * PropertyExpense and CorporateExpense do) — the foreign key column
     * name is read from that relation, so this method never needs to know
     * whether it's dealing with a property_expense_id or a
     * corporate_expense_id.
     */
    public function replaceSchedule($expense, array $cleanRows): void
    {
        DB::transaction(function () use ($expense, $cleanRows) {
            $relation = $expense->paymentSchedule();
            $foreignKey = $relation->getForeignKeyName();

            $relation->delete();

            if (empty($cleanRows)) {
                return;
            }

            $now = now();
            $rows = array_map(fn ($row) => array_merge($row, [
                'company_id' => $expense->company_id,
                $foreignKey  => $expense->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]), $cleanRows);

            $relation->getRelated()->newQuery()->insert($rows);
        });
    }

    /**
     * The core forecasting computation: walk this expense's schedule rows
     * oldest-to-newest, consuming them against the total already paid, and
     * return only the still-outstanding portion of each row (with its own
     * forecasted date) — exactly the "deduct paid amount from the
     * forecasted payment, oldest to newest" rule this feature was built
     * for.
     *
     * Returns an EMPTY collection if the expense has no schedule rows at
     * all (e.g. it predates this feature) — callers should treat that as
     * "no schedule exists" and fall back to whatever logic they used
     * before this feature existed, not as "nothing is owed."
     *
     * @return Collection<int, array{forecasted_date: string, amount: float, currency: string}>
     */
    public function outstandingRows($expense): Collection
    {
        $rows = $expense->paymentSchedule; // already ordered by forecasted_date via the relation

        if ($rows->isEmpty()) {
            return collect();
        }

        $pool = $expense->totalPaid();
        $currency = $expense->currency;

        $result = collect();

        foreach ($rows as $row) {
            $amount = (float) $row->amount;

            if ($pool >= $amount) {
                // This row is fully consumed by payments already made.
                $pool -= $amount;
                continue;
            }

            $remaining = round($amount - max(0, $pool), 2);
            $pool = 0;

            if ($remaining > 0) {
                $result->push([
                    'forecasted_date' => $row->forecasted_date->toDateString(),
                    'amount'          => $remaining,
                    'currency'        => $currency,
                ]);
            }
        }

        return $result;
    }
}
