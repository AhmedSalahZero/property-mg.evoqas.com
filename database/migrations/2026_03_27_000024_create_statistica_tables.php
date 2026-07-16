<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistica_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // e.g. "USD / EGP"
            $table->string('slug')->nullable();              // e.g. "usd-egp"
            $table->string('category');                      // fx_rates | oil_energy | commodities | interest_rates | custom
            $table->string('unit')->default('');             // e.g. "EGP", "USD/bbl", "%"
            $table->enum('frequency', ['daily','weekly','monthly','quarterly'])->default('daily');
            $table->string('color')->default('#3b82f6');     // hex color for chart line
            $table->text('description')->nullable();
            $table->string('source')->nullable();            // e.g. "CBE", "Bloomberg"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('statistica_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('statistica_series')->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('value', 20, 6);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series_id', 'entry_date']); // one value per date per series
            $table->index(['series_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistica_entries');
        Schema::dropIfExists('statistica_series');
    }
};