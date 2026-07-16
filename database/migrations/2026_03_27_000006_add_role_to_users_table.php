<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ══════════════════════════════════════════════════════════════════
//  VERO Property Management — Add Role to Users Table
//
//  Roles:
//    company_admin → Full access + user management (max 3 per company)
//    manager       → Full access, no user management
//    analyst       → Read + write data, no delete / settings
//    viewer        → Read-only results
//
//  Super Admins (is_super_admin = true) have no role —
//  they operate across all companies.
// ══════════════════════════════════════════════════════════════════

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'company_admin',
                'manager',
                'sales_manager',
                'analyst',
                'viewer',
            ])->nullable()->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
