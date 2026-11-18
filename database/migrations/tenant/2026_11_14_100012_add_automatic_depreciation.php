<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An asset may lose its value by itself, month after month, for the months of its life.
 * A depreciation paper says which asset it is for (empty: every asset of the branch) and
 * whether the system recorded it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->boolean('auto_depreciate')->default(true)->after('useful_life_months');
        });
        Schema::table('depreciation_runs', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('is_auto')->default(false)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('depreciation_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_asset_id');
            $table->dropColumn('is_auto');
        });
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('auto_depreciate');
        });
    }
};
