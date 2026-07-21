<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix for audit finding H-5 — RentContractController::update() has always
 * included 'updated_by' => auth()->id() in its $contract->update([...])
 * call, but the column never existed and 'updated_by' was never in
 * RentContract::$fillable. Laravel's mass-assignment protection silently
 * dropped it on every single contract edit — no error, just no effect —
 * so there has never actually been a record of who last edited a contract,
 * despite the code appearing to write one. This adds the real column so
 * that existing controller line finally does what it always looked like
 * it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
