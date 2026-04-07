<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * customer_credit_criteria_settings
     * ───────────────────────────────────────────────────────────────────────
     * Stores per-company configuration for the 8 Customer Credit Rating
     * criteria agreed for VERO Property Management.
     *
     * Criteria types:
     *   qualitative  → options stored in JSON, each option has a score (0-100)
     *   quantitative → numeric threshold with operator (gte / lte) + score
     *
     * The weight column (0-100) controls how much this criterion contributes
     * to the final customer credit score. All active weights should sum to 100.
     *
     * Agreed criteria keys:
     *   sector_player        → qualitative  (Yes / Partially / No)
     *   strategic_value      → qualitative  (Yes / No)
     *   years_in_market      → qualitative  (<5 / 5-10 / 10-20 / >20)
     *   company_type         → qualitative  (Multinational / Regional / Local)
     *   payment_behavior     → qualitative  (Fully Compliant / Minor Delays / Frequent Delays / Non-Compliant)
     *   sales_contribution   → quantitative (% threshold — gte/lte + value)
     *   years_with_customer  → qualitative  (<1 / 1-3 / 3-5 / >5)
     *   contract_formality   → qualitative  (Always Contracted / Mixed / Rarely Contracted)
     */
    public function up(): void
    {
        Schema::create('customer_credit_criteria_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Criterion identity
            $table->string('criterion_key', 80);   // e.g. 'sector_player'
            $table->string('criterion_label', 255); // e.g. 'Main Player in Sector'

            // Type determines how scoring works
            $table->enum('criterion_type', ['qualitative', 'quantitative'])->default('qualitative');

            // For qualitative: JSON array of {option_label, score}
            // e.g. [{"label":"Yes","score":100},{"label":"Partially","score":60},{"label":"No","score":0}]
            $table->json('options')->nullable();

            // For quantitative only (sales_contribution)
            $table->enum('threshold_operator', ['gte', 'lte'])->nullable();
            $table->decimal('threshold_value', 8, 2)->nullable();
            $table->string('unit', 50)->nullable(); // e.g. '%'

            // Scoring weight — how much this criterion contributes to final score
            $table->decimal('weight', 6, 2)->default(0.00); // 0-100, all active weights sum to 100

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'criterion_key'], 'ccc_settings_company_criterion_unique');
            $table->index('company_id', 'ccc_settings_company_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_criteria_settings');
    }
};