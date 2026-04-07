<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_uploads', function (Blueprint $table) {
            // Add missing error_message column
            $table->text('error_message')->nullable()->after('status');
        });

        // Fix the status enum to include 'done' AND 'completed'
        // The job uses 'completed' but the enum only has 'done'
        DB::statement("ALTER TABLE `sales_uploads` MODIFY COLUMN `status` 
            ENUM('processing','done','completed','failed') 
            NOT NULL DEFAULT 'processing'");
    }

    public function down(): void
    {
        Schema::table('sales_uploads', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        DB::statement("ALTER TABLE `sales_uploads` MODIFY COLUMN `status` 
            ENUM('processing','done','failed') 
            NOT NULL DEFAULT 'processing'");
    }
};