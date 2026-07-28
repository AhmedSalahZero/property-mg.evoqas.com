<?php

namespace App\Console\Commands;

use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One-off fix (July 2026) — corrects the YYMM segment of property/unit
 * codes that were generated before the acquisition_date parsing bug was
 * fixed (codes carried today's date, e.g. "2607", instead of the real
 * acquisition date, e.g. "2503"). Only touches codes that still look
 * auto-generated (PREFIX-TYPE-YYMM-SEQ, e.g. RAM-LND-2607-0001) — anything
 * that doesn't match that exact shape (a manually-typed code) is left
 * untouched. Sequence numbers are never changed, only the YYMM portion —
 * so this is a surgical swap, not a full regeneration.
 */
class FixPropertyCodes extends Command
{
    protected $signature = 'property:fix-codes {company_id?}';

    protected $description = 'Corrects the YYMM segment in auto-generated property/unit codes (e.g. 2607 -> 2503) to match the real acquisition date.';

    public function handle(): int
    {
        $companyId = $this->argument('company_id');

        $query = Property::query()->with('units');
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $fixed = 0;

        foreach ($query->get() as $property) {
            $currentCode = $property->property_code;
            $correctYymm = $this->correctYymm($property);

            if ($correctYymm !== null) {
                $newCode = $this->swapYymm($currentCode, $correctYymm);
                if ($newCode && $newCode !== $currentCode) {
                    $this->line("Property #{$property->id}: {$currentCode} -> {$newCode}");
                    $property->update(['property_code' => $newCode]);
                    $currentCode = $newCode;
                    $fixed++;
                }
            }

            // Re-sync every child unit's embedded prefix against the
            // CURRENT parent code, regardless of whether the parent's own
            // code just changed above. This is what actually catches units
            // left stale by an earlier run that already fixed the parent
            // but — because of the bug this replaces — never touched its
            // children (e.g. RAM-LND-2607-0001-UNIT-0021 stayed "2607"
            // even after the parent became RAM-LND-2503-0001).
            foreach ($property->units as $unit) {
                if (!$unit->unit_code) {
                    continue;
                }
                if (!preg_match('/^(.*)(-UNIT-\d{4})$/', $unit->unit_code, $m)) {
                    continue; // doesn't look auto-generated — leave it alone
                }
                [, $existingPrefix, $suffix] = $m;
                if ($existingPrefix === $currentCode) {
                    continue; // already matches the parent — nothing to do
                }

                $newUnitCode = $currentCode . $suffix;
                $this->line("  Unit #{$unit->id}: {$unit->unit_code} -> {$newUnitCode}");
                $unit->update(['unit_code' => $newUnitCode]);
                $fixed++;
            }
        }

        $this->info("Done. {$fixed} code(s) corrected.");
        return self::SUCCESS;
    }

    // Unit nature -> its own acquisition_date. Building/Land/Complex have
    // none of their own, so use the EARLIEST child unit's acquisition_date
    // instead (same rule the fixed generator now uses going forward).
    private function correctYymm(Property $property): ?string
    {
        if ($property->nature === 'unit') {
            if (empty($property->acquisition_date)) {
                return null;
            }
            return $this->parseMonthYearOrToday($property->acquisition_date)->format('ym');
        }

        $earliest = null;
        foreach ($property->units as $unit) {
            if (empty($unit->acquisition_date)) {
                continue;
            }
            $parsed = $this->parseMonthYearOrToday($unit->acquisition_date);
            if ($earliest === null || $parsed->lt($earliest)) {
                $earliest = $parsed;
            }
        }

        return $earliest?->format('ym');
    }

    // Only swaps the YYMM segment of a code that still matches the exact
    // auto-generated shape PREFIX-TYPE-YYMM-SEQ — anything else (a
    // manually-typed code) is left completely alone.
    private function swapYymm(?string $code, string $correctYymm): ?string
    {
        if (!$code) {
            return null;
        }
        if (preg_match('/^([A-Z]{3}-(?:UNT|BLD|LND|CPX|PRO))-(\d{4})-(\d{4})$/', $code, $m)) {
            return "{$m[1]}-{$correctYymm}-{$m[3]}";
        }
        return null;
    }

    // Same dual-format parser as the fixed generator — accepts the real
    // "YYYY-MM" stored by the native month input, and the legacy "MM/YYYY".
    private function parseMonthYearOrToday(?string $value): Carbon
    {
        if (empty($value)) {
            return Carbon::today();
        }
        $value = trim($value);
        try {
            if (preg_match('#^\d{4}-\d{1,2}$#', $value)) {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            }
            return Carbon::createFromFormat('m/Y', $value)->startOfMonth();
        } catch (\Exception $e) {
            return Carbon::today();
        }
    }
}
