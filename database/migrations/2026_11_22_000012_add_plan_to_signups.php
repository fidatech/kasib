<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The plan a business chose when it registered on the website: starter or pro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signups', function (Blueprint $table) {
            $table->string('plan', 20)->default('starter');
        });
    }

    public function down(): void
    {
        Schema::table('signups', fn (Blueprint $table) => $table->dropColumn('plan'));
    }
};
