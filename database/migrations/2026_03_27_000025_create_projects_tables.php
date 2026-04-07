<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Projects ────────────────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phase')->nullable();            // e.g. "Phase 1: Due Diligence"
            $table->enum('status', ['not_started', 'in_progress', 'on_hold', 'completed', 'cancelled'])
                  ->default('not_started');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->timestamps();
        });

        // ── 2. Project Tasks ───────────────────────────────────────────────────
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('depends_on_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'blocked'])
                  ->default('not_started');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->integer('order')->default(0);           // ordering within project
            $table->integer('estimated_days')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('progress_pct')->default(0);    // 0–100
            $table->timestamps();
        });

        // ── 3. Task Assignees (many-to-many) ───────────────────────────────────
        Schema::create('project_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_task_id', 'user_id']);
        });

        // ── 4. Task Time Logs ──────────────────────────────────────────────────
        Schema::create('project_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('hours', 6, 2)->default(0);     // hours worked
            $table->text('notes')->nullable();
            $table->integer('progress_pct')->nullable();    // optional progress update
            $table->timestamps();
        });

        // ── 5. Project Expenses ────────────────────────────────────────────────
        Schema::create('project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('category', ['consultant', 'software', 'purchase', 'subscription', 'travel', 'other']);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('receipt_path')->nullable();     // stored in private disk
            $table->timestamps();
        });

        // ── 6. User Cost Rates (per company) ──────────────────────────────────
        Schema::create('user_cost_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cost_rates');
        Schema::dropIfExists('project_expenses');
        Schema::dropIfExists('project_task_logs');
        Schema::dropIfExists('project_task_assignees');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
    }
};