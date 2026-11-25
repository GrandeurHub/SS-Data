<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indexed_files', function (Blueprint $table) {
            $table->text('tags')->nullable()->after('region'); // JSON list of keywords
        });
    }

    public function down(): void
    {
        Schema::table('indexed_files', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
