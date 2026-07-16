<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Models\Company;

class StatisticaController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function authorizeCompany(Company $company): void
    {
        $user = Auth::user();
        if ($user->is_super_admin) return;
        if ((int) $user->company_id !== (int) $company->id) abort(403);
    }

    private function authorizeWrite(Company $company): void
    {
        $user = Auth::user();
        if ($user->is_super_admin) return;
        if ((int) $user->company_id !== (int) $company->id) abort(403);
        // Only super_admin, company_admin, and manager can write
        if (!in_array($user->role, ['company_admin', 'manager'])) {
            abort(403, 'Only admins and managers can modify Statistica data.');
        }
    }

    private function authorizeSeries(Company $company, $seriesId): void
    {
        $series = DB::table('statistica_series')
            ->where('id', (int) $seriesId)
            ->where('company_id', (int) $company->id)
            ->first();
        if (!$series) abort(404, 'Series not found.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — list all series for the company
    // GET /companies/{company}/statistica
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $series = DB::table('statistica_series')
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $series = $series->map(function ($s) {
            $entries = DB::table('statistica_entries')
                ->where('series_id', $s->id)
                ->orderBy('entry_date')
                ->get(['entry_date', 'value']);

            $count  = $entries->count();
            $latest = $entries->last();
            $prev   = $count >= 2 ? $entries->get($count - 2) : null;

            $change    = null;
            $changePct = null;
            if ($latest && $prev && $prev->value != 0) {
                $change    = round((float)$latest->value - (float)$prev->value, 4);
                $changePct = round((($latest->value - $prev->value) / abs($prev->value)) * 100, 2);
            }

            $sparkline = $entries->slice(-20)->values()->map(fn($e) => [
                'date'  => $e->entry_date,
                'value' => (float) $e->value,
            ]);

            return [
                'id'           => $s->id,
                'name'         => $s->name,
                'category'     => $s->category,
                'unit'         => $s->unit,
                'frequency'    => $s->frequency,
                'color'        => $s->color,
                'description'  => $s->description,
                'source'       => $s->source,
                'is_active'    => (bool) $s->is_active,
                'sort_order'   => $s->sort_order,
                'entry_count'  => $count,
                'latest_value' => $latest ? (float) $latest->value : null,
                'latest_date'  => $latest ? $latest->entry_date : null,
                'change'       => $change,
                'change_pct'   => $changePct,
                'sparkline'    => $sparkline,
            ];
        });

        return Inertia::render('Statistica/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'series'  => $series,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE — create new series
    // POST /companies/{company}/statistica
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, Company $company)
    {
        $this->authorizeWrite($company);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'string', 'in:fx_rates,oil_energy,commodities,interest_rates,custom'],
            'unit'        => ['nullable', 'string', 'max:30'],
            'frequency'   => ['required', 'in:daily,weekly,monthly,quarterly'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'source'      => ['nullable', 'string', 'max:100'],
        ]);

        $maxOrder = DB::table('statistica_series')
            ->where('company_id', $company->id)
            ->max('sort_order') ?? 0;

        $id = DB::table('statistica_series')->insertGetId([
            'company_id'  => $company->id,
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'unit'        => $validated['unit'] ?? '',
            'frequency'   => $validated['frequency'],
            'color'       => $validated['color'] ?? '#3b82f6',
            'description' => $validated['description'] ?? null,
            'source'      => $validated['source'] ?? null,
            'is_active'   => true,
            'sort_order'  => $maxOrder + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('company.statistica.show', ['company' => $company->id, 'seriesId' => $id])
            ->with('flash', ['success' => 'Series created successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW — series detail page with chart + entries + forecast
    // GET /companies/{company}/statistica/{seriesId}
    // ─────────────────────────────────────────────────────────────────────────
    public function show(Company $company, $seriesId)
    {
        $this->authorizeCompany($company);
        $this->authorizeSeries($company, $seriesId);

        $series  = DB::table('statistica_series')->find($seriesId);

        // ── Date range filter — default to last 1 year ──────────────────────
        $startDate = request('start_date')
            ? Carbon::parse(request('start_date'))->format('Y-m-d')
            : Carbon::now()->subYear()->format('Y-m-d');

        $endDate = request('end_date')
            ? Carbon::parse(request('end_date'))->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        // "All" mode: both params explicitly set to empty string
        $filterByDate = !(request()->has('start_date') && request('start_date') === '' && request('end_date') === '');

        $query = DB::table('statistica_entries as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
            ->where('e.series_id', $seriesId)
            ->orderBy('e.entry_date')
            ->select('e.id', 'e.entry_date', 'e.value', 'e.notes', 'e.created_at',
                     'u.name as created_by_name');

        if ($filterByDate) {
            $query->whereBetween('e.entry_date', [$startDate, $endDate]);
        }

        $entries = $query->get()->map(fn($e) => [
            'id'              => $e->id,
            'entry_date'      => $e->entry_date,
            'value'           => (float) $e->value,
            'notes'           => $e->notes,
            'created_at'      => $e->created_at,
            'created_by_name' => $e->created_by_name,
        ]);

        // Forecast & growth always use all data (unfiltered) for accuracy
        $allEntries = DB::table('statistica_entries')
            ->where('series_id', $seriesId)
            ->orderBy('entry_date')
            ->get(['entry_date', 'value'])
            ->map(fn($e) => ['entry_date' => $e->entry_date, 'value' => (float) $e->value])
            ->toArray();

        $forecast = $this->buildForecast($allEntries, $series->frequency);
        $growth   = $this->calcGrowthRates($allEntries, $series->frequency);

        return Inertia::render('Statistica/Show', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'series'  => [
                'id'          => $series->id,
                'name'        => $series->name,
                'category'    => $series->category,
                'unit'        => $series->unit,
                'frequency'   => $series->frequency,
                'color'       => $series->color,
                'description' => $series->description,
                'source'      => $series->source,
                'is_active'   => (bool) $series->is_active,
            ],
            'entries'    => $entries,
            'forecast'   => $forecast,
            'growth'     => $growth,
            'filters'    => [
                'start_date' => $filterByDate ? $startDate : '',
                'end_date'   => $filterByDate ? $endDate   : '',
                'mode'       => $filterByDate ? 'range' : 'all',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE — edit series metadata
    // PUT /companies/{company}/statistica/{seriesId}
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, Company $company, $seriesId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'string', 'in:fx_rates,oil_energy,commodities,interest_rates,custom'],
            'unit'        => ['nullable', 'string', 'max:30'],
            'frequency'   => ['required', 'in:daily,weekly,monthly,quarterly'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'source'      => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('statistica_series')->where('id', $seriesId)->update([
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'unit'        => $validated['unit'] ?? '',
            'frequency'   => $validated['frequency'],
            'color'       => $validated['color'] ?? '#3b82f6',
            'description' => $validated['description'] ?? null,
            'source'      => $validated['source'] ?? null,
            'updated_at'  => now(),
        ]);

        return back()->with('flash', ['success' => 'Series updated.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY — delete series + all entries
    // DELETE /companies/{company}/statistica/{seriesId}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(Company $company, $seriesId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        DB::table('statistica_entries')->where('series_id', $seriesId)->delete();
        DB::table('statistica_series')->where('id', $seriesId)->delete();

        return redirect()->route('company.statistica.index', ['company' => $company->id])
            ->with('flash', ['success' => 'Series deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE ENTRY — add a single data point
    // POST /companies/{company}/statistica/{seriesId}/entries
    // ─────────────────────────────────────────────────────────────────────────
    public function storeEntry(Request $request, Company $company, $seriesId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'value'      => ['required', 'numeric'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $exists = DB::table('statistica_entries')
            ->where('series_id', $seriesId)
            ->where('entry_date', $validated['entry_date'])
            ->first();

        if ($exists) {
            DB::table('statistica_entries')->where('id', $exists->id)->update([
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('statistica_entries')->insert([
                'series_id'  => $seriesId,
                'entry_date' => $validated['entry_date'],
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('flash', ['success' => 'Entry saved.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE ENTRY
    // PUT /companies/{company}/statistica/{seriesId}/entries/{entryId}
    // ─────────────────────────────────────────────────────────────────────────
    public function updateEntry(Request $request, Company $company, $seriesId, $entryId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'value'      => ['required', 'numeric'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('statistica_entries')
            ->where('id', $entryId)
            ->where('series_id', $seriesId)
            ->update([
                'entry_date' => $validated['entry_date'],
                'value'      => $validated['value'],
                'notes'      => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with('flash', ['success' => 'Entry updated.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY ENTRY
    // DELETE /companies/{company}/statistica/{seriesId}/entries/{entryId}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroyEntry(Company $company, $seriesId, $entryId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        DB::table('statistica_entries')
            ->where('id', $entryId)
            ->where('series_id', $seriesId)
            ->delete();

        return back()->with('flash', ['success' => 'Entry deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BULK DELETE ENTRIES
    // DELETE /companies/{company}/statistica/{seriesId}/entries/bulk
    // ─────────────────────────────────────────────────────────────────────────
    public function bulkDeleteEntries(Request $request, Company $company, $seriesId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = DB::table('statistica_entries')
            ->where('series_id', $seriesId)
            ->whereIn('id', $validated['ids'])
            ->delete();

        return back()->with('flash', ['success' => "{$deleted} " . ($deleted === 1 ? 'entry' : 'entries') . " deleted."]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORT CSV — bulk upload entries from CSV file
    // POST /companies/{company}/statistica/{seriesId}/import
    // ─────────────────────────────────────────────────────────────────────────
    public function importCsv(Request $request, Company $company, $seriesId)
    {
        $this->authorizeWrite($company);
        $this->authorizeSeries($company, $seriesId);

        // Extension-only validation — MIME sniffing is unreliable for CSV on Windows
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'])) {
            return back()->withErrors(['file' => 'Only CSV or Excel (.xlsx / .xls) files are accepted.']);
        }

        $imported = 0;
        $skipped  = 0;
        $rows     = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            // ── Excel path ──────────────────────────────────────────────────
            $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(false); // must be false to read date formats
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();

            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                // ── Date cell (column A) ──
                $dateCell = $sheet->getCell('A' . $rowIndex);
                $rawDate  = $dateCell->getValue();

                if (empty($rawDate) && $rawDate !== 0) continue;

                // Excel stores dates as numeric serials — convert properly
                if (is_numeric($rawDate) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($dateCell)) {
                    $dateStr = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d');
                } else {
                    $dateStr = trim((string) $rawDate);
                }

                // ── Value cell (column B) ──
                $valueCell = $sheet->getCell('B' . $rowIndex);
                $rawValue  = $valueCell->getValue();

                if ($rawValue === null || $rawValue === '') continue;

                $rows[] = ['date' => $dateStr, 'value' => (string) $rawValue];
            }
        } else {
            // ── CSV path — BOM-safe ──────────────────────────────────────────
            $handle = fopen($file->getRealPath(), 'r');
            $bom    = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle); // no BOM — rewind to start
            }
            fgetcsv($handle); // skip header row
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2 && trim($row[0]) !== '') {
                    $rows[] = ['date' => trim($row[0]), 'value' => trim($row[1])];
                }
            }
            fclose($handle);
        }

        foreach ($rows as $row) {
            try {
                if (empty($row['date'])) { $skipped++; continue; }

                // Handle Excel date serials that slipped through as plain numbers
                if (is_numeric($row['date']) && (int)$row['date'] > 1000) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$row['date'])->format('Y-m-d');
                } else {
                    $date = Carbon::parse($row['date'])->format('Y-m-d');
                }

                $value = is_numeric($row['value']) ? (float) $row['value'] : null;
                if ($value === null) { $skipped++; continue; }

                $exists = DB::table('statistica_entries')
                    ->where('series_id', $seriesId)
                    ->where('entry_date', $date)
                    ->first();

                if ($exists) {
                    DB::table('statistica_entries')->where('id', $exists->id)
                        ->update(['value' => $value, 'updated_at' => now()]);
                } else {
                    DB::table('statistica_entries')->insert([
                        'series_id'  => $seriesId,
                        'entry_date' => $date,
                        'value'      => $value,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }

        return back()->with('flash', ['success' => "{$imported} entries imported, {$skipped} skipped."]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPARE PAGE
    // GET /companies/{company}/statistica/compare
    // ─────────────────────────────────────────────────────────────────────────
    public function compare(Company $company)
    {
        $this->authorizeCompany($company);

        $allSeries = DB::table('statistica_series')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'unit', 'color', 'frequency'])
            ->map(fn($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'category'  => $s->category,
                'unit'      => $s->unit,
                'color'     => $s->color,
                'frequency' => $s->frequency,
            ]);

        $selectedIds = array_filter(array_map('intval', explode(',', request('series', ''))));
        $seriesData  = [];

        foreach ($selectedIds as $sid) {
            $s = DB::table('statistica_series')
                ->where('id', $sid)
                ->where('company_id', $company->id)
                ->first();
            if (!$s) continue;

            $entries = DB::table('statistica_entries')
                ->where('series_id', $sid)
                ->orderBy('entry_date')
                ->pluck('value', 'entry_date')
                ->map(fn($v) => (float) $v);

            $seriesData[] = [
                'id'      => $s->id,
                'name'    => $s->name,
                'unit'    => $s->unit,
                'color'   => $s->color,
                'entries' => $entries,
            ];
        }

        return Inertia::render('Statistica/Compare', [
            'company'    => ['id' => $company->id, 'name' => $company->name],
            'allSeries'  => $allSeries,
            'seriesData' => $seriesData,
            'selected'   => $selectedIds,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE
    // GET /companies/{company}/statistica/template
    // ─────────────────────────────────────────────────────────────────────────
    public function downloadTemplate(Company $company)
    {
        $this->authorizeCompany($company);

        $csv  = "Date,Value,Notes\n";
        $csv .= "2026-01-01,30.50,Optional note\n";
        $csv .= "2026-01-02,30.55,\n";
        $csv .= "2026-01-03,30.48,\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="statistica_import_template.csv"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — Holt-Winters Double Exponential Smoothing Forecast
    // ─────────────────────────────────────────────────────────────────────────
    private function buildForecast(array $entries, string $frequency): array
    {
        if (count($entries) < 4) return [];

        $values = array_column($entries, 'value');
        $dates  = array_column($entries, 'entry_date');
        $n      = count($values);

        $alpha = 0.3;
        $beta  = 0.1;
        $level = $values[0];
        $trend = ($values[min(3, $n-1)] - $values[0]) / min(3, $n-1);

        for ($i = 1; $i < $n; $i++) {
            $prevLevel = $level;
            $level     = $alpha * $values[$i] + (1 - $alpha) * ($prevLevel + $trend);
            $trend     = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
        }

        $steps = match($frequency) {
            'daily'     => 30,
            'weekly'    => 12,
            'monthly'   => 6,
            'quarterly' => 4,
            default     => 12,
        };

        $stepDays = match($frequency) {
            'daily'     => 1,
            'weekly'    => 7,
            'monthly'   => 30,
            'quarterly' => 91,
            default     => 1,
        };

        $lastDate = Carbon::parse(end($dates));
        $mean     = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / $n;
        $stdDev   = sqrt($variance);
        $forecast = [];

        for ($h = 1; $h <= $steps; $h++) {
            $projDate  = $lastDate->copy()->addDays($stepDays * $h);
            $projValue = $level + ($h * $trend);
            $bandWidth = $stdDev * 1.96 * sqrt($h / $n * 2 + 0.1);

            $forecast[] = [
                'date'  => $projDate->format('Y-m-d'),
                'value' => round($projValue, 4),
                'upper' => round($projValue + $bandWidth, 4),
                'lower' => round(max(0, $projValue - $bandWidth), 4),
            ];
        }

        return $forecast;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — Growth rate calculations
    // ─────────────────────────────────────────────────────────────────────────
    private function calcGrowthRates(array $entries, string $frequency): array
    {
        if (count($entries) < 2) return [];

        $values = array_column($entries, 'value');
        $dates  = array_column($entries, 'entry_date');
        $n      = count($values);

        $popChanges = [];
        for ($i = 1; $i < $n; $i++) {
            $prev = (float) $values[$i - 1];
            $curr = (float) $values[$i];
            if ($prev == 0) continue;
            $popChanges[] = [
                'date'   => $dates[$i],
                'change' => round($curr - $prev, 4),
                'pct'    => round((($curr - $prev) / abs($prev)) * 100, 2),
            ];
        }

        $yoyChanges = [];
        $dateMap    = array_combine($dates, $values);
        foreach ($entries as $e) {
            $d    = Carbon::parse($e['entry_date']);
            $past = $d->copy()->subYear()->format('Y-m-d');
            if (isset($dateMap[$past]) && $dateMap[$past] != 0) {
                $curr = (float) $e['value'];
                $prev = (float) $dateMap[$past];
                $yoyChanges[] = [
                    'date' => $e['entry_date'],
                    'pct'  => round((($curr - $prev) / abs($prev)) * 100, 2),
                ];
            }
        }

        return [
            'pop' => $popChanges,
            'yoy' => $yoyChanges,
        ];
    }
}