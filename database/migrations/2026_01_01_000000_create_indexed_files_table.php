<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexed_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');      // prices | productions | imports | exports
            $table->string('item');          // Palay, Rice, Fertilizer ...
            $table->string('period_type');   // weekly | monthly | semester | yearly
            $table->string('period_value');  // 1-52 | January-December | 1st/2nd | 1970-2026
            $table->string('region')->nullable(); // Productions only
            $table->string('original_name');
            $table->string('path');
            $table->string('extension', 10);
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['category', 'item', 'period_type', 'period_value'], 'indexed_files_lookup');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indexed_files');
    }
};
