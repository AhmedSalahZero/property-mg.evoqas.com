<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — Alerts Table
//  System-wide notification/alert log per company.
//
//  Alert types:
//    missed_revenue    → Revenue target not met
//    late_report       → Report/section not submitted on time
//    low_margin        → Profitability margin below threshold
//    kpi_threshold     → A KPI value breached its defined limit
//    contract_expiry   → A contract approaching or past expiry date
//    task_overdue      → A user task past its expected end date
//    other             → General / manual alerts
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', [
                'missed_revenue',
                'late_report',
                'low_margin',
                'kpi_threshold',
                'contract_expiry',
                'task_overdue',
                'other',
            ]);

            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
