<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // People log in with a username. It stays nullable so rows created before this migration remain valid.
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('role', 20)->default('client')->after('password'); // administrator | client
            $table->softDeletes();
        });

        // Accounts made from the Accounts screen only need a username, not an e-mail address.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::table('indexed_files', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('indexed_files', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
        });
    }
};
