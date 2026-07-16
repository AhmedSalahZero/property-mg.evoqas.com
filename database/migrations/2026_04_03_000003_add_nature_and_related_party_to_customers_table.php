<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('tenant_nature', ['individual', 'corporate'])
                  ->nullable()
                  ->after('business_sector');

            $table->boolean('is_related_party')
                  ->default(false)
                  ->after('tenant_nature');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tenant_nature', 'is_related_party']);
        });
    }
};