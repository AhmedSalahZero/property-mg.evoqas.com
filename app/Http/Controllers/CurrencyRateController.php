<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Controllers\Concerns\AuthorizesCompany;

/**
 * Manages the currency_rates table that CurrencyConversionService reads from
 * — the fix for audit finding C4. Every rent revenue/collection, property
 * expense/payment, and installment due gets converted to the company's base
 * currency (companies.currency) using the rate closest to its date here.
 */
class CurrencyRateController extends Controller
{
    use AuthorizesCompany;

    // ═══════════════════════════════════════════════════════════════════
    // INDEX
    // ═══════════════════════════════════════════════════════════════════
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $rates = CurrencyRate::forCompany($company->id)
            ->orderByDesc('rate_date')
            ->orderBy('currency')
            ->get(['id', 'currency', 'rate_date', 'rate', 'source'])
            ->map(fn ($r) => [
                'id'        => $r->id,
                'currency'  => $r->currency,
                'rate_date' => $r->rate_date->format('Y-m-d'),
                'rate'      => (float) $r->rate,
                'source'    => $r->source,
            ]);

        // Statistica series available to pull from — this app already has a
        // fully working "Statistica" tab for tracking FX/commodity/rate
        // trends over time. Rather than build a second data-entry system, the
        // Exchange Rates page lets the user explicitly pick one of these
        // series (any category, though fx_rates is the obvious fit) and pull
        // its entries in as rates for a chosen currency, on demand — no
        // schema changes to Statistica, no automatic/fragile matching.
        $statisticaSeries = DB::table('statistica_series')
            ->where('company_id', $company->id)
            ->orderByRaw("CASE WHEN category = 'fx_rates' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'unit'])
            ->map(fn ($s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'category' => $s->category,
                'unit'     => $s->unit,
                'entry_count' => DB::table('statistica_entries')->where('series_id', $s->id)->count(),
            ]);

        // Moved from CompanySettings/CurrencyRates to Reports/CurrencyRates
        // (July 2026, confirmed request) — Exchange Rates now lives under
        // Properties > Reports rather than Company Settings.
        return Inertia::render('Reports/CurrencyRates', [
            'company'          => $company,
            'baseCurrency'     => strtoupper($company->currency ?: 'EGP'),
            'rates'            => $rates,
            'currencyOptions'  => $this->currencyOptions($company),
            'statisticaSeries' => $statisticaSeries,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE / UPSERT ONE RATE
    // ═══════════════════════════════════════════════════════════════════
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'currency'  => 'required|string|max:10',
            'rate_date' => 'required|date',
            'rate'      => 'required|numeric|min:0.000001',
        ]);

        $currency = strtoupper($data['currency']);

        if ($currency === strtoupper($company->currency ?: 'EGP')) {
            return back()->withErrors(['currency' => 'Cannot set a rate for the company\'s own base currency.']);
        }

        CurrencyRate::updateOrCreate(
            ['company_id' => $company->id, 'currency' => $currency, 'rate_date' => $data['rate_date']],
            ['rate' => $data['rate'], 'source' => CurrencyRate::SOURCE_MANUAL, 'created_by' => auth()->id()]
        );

        return back()->with('success', "Exchange rate for {$currency} on {$data['rate_date']} saved.");
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY
    // ═══════════════════════════════════════════════════════════════════
    public function destroy(Company $company, CurrencyRate $rate)
    {
        $this->authorizeCompany($company);
        abort_unless($rate->company_id === $company->id, 403);

        $rate->delete();

        return back()->with('success', 'Exchange rate deleted.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // PULL FROM STATISTICA — copy a Statistica series' entries in as rates
    // for a chosen currency, on demand. No live link: this is a one-time
    // (re-runnable) copy, same as an Excel import, just sourced from data
    // that's already in the app instead of a spreadsheet.
    // ═══════════════════════════════════════════════════════════════════
    public function importFromStatistica(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'currency'  => 'required|string|max:10',
            'series_id' => 'required|integer',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $currency = strtoupper($data['currency']);
        $base     = strtoupper($company->currency ?: 'EGP');

        if ($currency === $base) {
            return back()->withErrors(['currency' => "Cannot set a rate for the company's own base currency."]);
        }

        // Confirm the series actually belongs to this company before reading it.
        $series = DB::table('statistica_series')
            ->where('id', $data['series_id'])
            ->where('company_id', $company->id)
            ->first();

        if (!$series) {
            return back()->withErrors(['series_id' => 'Statistica series not found for this company.']);
        }

        $entries = DB::table('statistica_entries')
            ->where('series_id', $series->id)
            ->when($data['date_from'] ?? null, fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($data['date_to'] ?? null, fn ($q, $d) => $q->where('entry_date', '<=', $d))
            ->orderBy('entry_date')
            ->get(['entry_date', 'value']);

        if ($entries->isEmpty()) {
            return back()->withErrors(['series_id' => "\"{$series->name}\" has no entries in that range."]);
        }

        $imported = 0;
        foreach ($entries as $e) {
            CurrencyRate::updateOrCreate(
                ['company_id' => $company->id, 'currency' => $currency, 'rate_date' => $e->entry_date],
                ['rate' => (float) $e->value, 'source' => CurrencyRate::SOURCE_STATISTICA_IMPORT, 'created_by' => auth()->id()]
            );
            $imported++;
        }

        return back()->with('success', "Pulled {$imported} rate(s) for {$currency} from Statistica series \"{$series->name}\".");
    }

    // ═══════════════════════════════════════════════════════════════════
    // DOWNLOAD TEMPLATE
    // ═══════════════════════════════════════════════════════════════════
    public function downloadTemplate(Company $company)
    {
        $this->authorizeCompany($company);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exchange Rates Template');

        $headers = ['currency', 'rate_date', 'rate'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        $base = strtoupper($company->currency ?: 'EGP');
        $sheet->setCellValue('A2', 'USD');
        $sheet->setCellValue('B2', now()->toDateString());
        $sheet->setCellValue('C2', '48.50');
        $sheet->getComment('C1')->getText()->createTextRun(
            "Rate = how many units of {$base} (your base currency) equal 1 unit of `currency`."
        );

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'exchange_rates_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPORT — download all current rates
    // ═══════════════════════════════════════════════════════════════════
    public function export(Company $company)
    {
        $this->authorizeCompany($company);

        $rates = CurrencyRate::forCompany($company->id)
            ->orderBy('currency')
            ->orderByDesc('rate_date')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exchange Rates');

        $headers = ['currency', 'rate_date', 'rate', 'source'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        $row = 2;
        foreach ($rates as $r) {
            $sheet->setCellValueByColumnAndRow(1, $row, $r->currency);
            $sheet->setCellValueByColumnAndRow(2, $row, $r->rate_date->format('Y-m-d'));
            $sheet->setCellValueByColumnAndRow(3, $row, (float) $r->rate);
            $sheet->setCellValueByColumnAndRow(4, $row, $r->source);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'exchange_rates_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // IMPORT — bulk upload rates from Excel
    // ═══════════════════════════════════════════════════════════════════
    public function import(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file   = $request->file('file');
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'Excel file must contain a header row plus at least one data row.']);
        }

        $headerMap = [];
        foreach ($rows[1] as $col => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') $headerMap[$key] = $col;
        }

        foreach (['currency', 'rate_date', 'rate'] as $required) {
            if (!isset($headerMap[$required])) {
                return back()->withErrors(['file' => "Missing required column: {$required}"]);
            }
        }

        $base = strtoupper($company->currency ?: 'EGP');
        $imported = 0;
        $skipped  = 0;

        for ($r = 2; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $empty = true;
            foreach ($row as $v) {
                if (trim((string) $v) !== '') { $empty = false; break; }
            }
            if ($empty) continue;

            $currency = strtoupper(trim((string) ($row[$headerMap['currency']] ?? '')));
            if ($currency === '' || $currency === $base) { $skipped++; continue; }

            $rawDate = $row[$headerMap['rate_date']] ?? null;
            $date = null;
            if (is_numeric($rawDate)) {
                $date = ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
            } elseif (!empty($rawDate)) {
                $ts = strtotime((string) $rawDate);
                $date = $ts ? date('Y-m-d', $ts) : null;
            }
            if (!$date) { $skipped++; continue; }

            $rateRaw = trim((string) ($row[$headerMap['rate']] ?? ''));
            if ($rateRaw === '' || !is_numeric($rateRaw) || (float) $rateRaw <= 0) { $skipped++; continue; }

            CurrencyRate::updateOrCreate(
                ['company_id' => $company->id, 'currency' => $currency, 'rate_date' => $date],
                ['rate' => (float) $rateRaw, 'source' => CurrencyRate::SOURCE_EXCEL_IMPORT, 'created_by' => auth()->id()]
            );
            $imported++;
        }

        return back()->with('success', "Imported {$imported} exchange rate(s)." . ($skipped > 0 ? " Skipped {$skipped} invalid/base-currency row(s)." : ''));
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════
    private function currencyOptions(Company $company): array
    {
        $all = ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'QAR'];
        $base = strtoupper($company->currency ?: 'EGP');

        return array_values(array_filter($all, fn ($c) => $c !== $base));
    }
}
