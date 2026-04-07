<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — Extend Users Table
//  Adds: company_id, is_super_admin, is_active,
//        phone, job_title, avatar, theme
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->boolean('is_super_admin')->default(false)->after('company_id');
            $table->boolean('is_active')->default(true)->after('is_super_admin');
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('job_title');
            $table->string('theme')->default('dark')->after('avatar'); // dark / light
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'is_super_admin',
                'is_active',
                'phone',
                'job_title',
                'avatar',
                'theme',
            ]);
        });
    }
};