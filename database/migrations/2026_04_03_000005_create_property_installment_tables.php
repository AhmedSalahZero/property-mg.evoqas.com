<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Installment Plan (form inputs — one per property) ─────────────────
        Schema::create('property_installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->enum('installment_type', ['regular', 'variable'])->default('regular');
            $table->string('currency', 10)->default('EGP');

            // Header
            $table->string('delivery_date', 7)->nullable();      // MM/YYYY
            $table->string('ready_to_use_date', 7)->nullable();  // MM/YYYY

            // Regular — Signing & Reservation
            $table->decimal('signing_amount', 15, 2)->nullable();
            $table->string('signing_date', 7)->nullable();        // MM/YYYY
            $table->decimal('reservation_amount', 15, 2)->nullable();
            $table->string('reservation_date', 7)->nullable();    // MM/YYYY

            // Regular — Installment rows stored as JSON
            // Each item: { amount, count, start_date (MM/YYYY), interval }
            $table->json('installment_rows')->nullable();

            // Regular — Annual Installments
            $table->boolean('has_annual')->default(false);
            $table->string('annual_start_date', 7)->nullable();
            $table->decimal('annual_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('annual_count')->nullable();

            // Regular — Delivery Payments
            $table->boolean('has_delivery')->default(false);
            $table->string('delivery_start_date', 7)->nullable();
            $table->decimal('delivery_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('delivery_count')->nullable();
            $table->enum('delivery_interval', ['monthly', 'quarterly', 'semi_annually'])->nullable();

            // Regular — Maintenance Payments
            $table->boolean('has_maintenance')->default(false);
            $table->string('maintenance_start_date', 7)->nullable();
            $table->decimal('maintenance_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('maintenance_count')->nullable();
            $table->enum('maintenance_interval', ['monthly', 'quarterly', 'semi_annually'])->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('property_id'); // one plan per property
        });

        // ── Installment Dues (generated schedule rows) ────────────────────────
        Schema::create('property_installment_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('property_installment_plans')->cascadeOnDelete();

            $table->enum('due_type', [
                'signing',
                'reservation',
                'installment',
                'annual',
                'delivery',
                'maintenance',
                'variable',
            ])->default('installment');

            $table->date('due_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'due_date']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_installment_dues');
        Schema::dropIfExists('property_installment_plans');
    }
};