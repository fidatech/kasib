<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The plan of a business: its name, and how many users and accounts it may have (empty: no limit). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan', 60)->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_accounts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'max_users', 'max_accounts']);
        });
    }
};
