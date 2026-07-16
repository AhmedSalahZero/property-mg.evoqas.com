<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('normalized_name', 150);
            $table->timestamps();

            $table->unique(['company_id', 'normalized_name']);
        });

        Schema::create('property_tag', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['property_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_tag');
        Schema::dropIfExists('tags');
    }
};
