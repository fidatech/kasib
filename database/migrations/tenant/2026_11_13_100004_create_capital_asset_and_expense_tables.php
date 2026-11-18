<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Shareholders and capital, funding of branches, fixed assets, expenses and liabilities. Each paper posts into the journal. */
return new class extends Migration
{
    public function up(): void
    {
        // shareholders belong to the licence, not to a branch
        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('father_name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->decimal('share_percent', 7, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('capital_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shareholder_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);                                   // contribution, withdrawal, guarantee
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // a guarantee: the branch it is paid for
            $table->date('date');
            $table->decimal('amount', 22, 2);                             // AFN
            $table->foreignId('money_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);                                   // loan (from the main branch) or own (the representative's money)
            $table->string('direction', 20);                              // increase, decrease
            $table->date('date');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('kind', 20);                                   // tangible, intangible
            $table->date('purchase_date');
            $table->decimal('cost', 22, 2);                               // AFN
            $table->decimal('salvage_value', 22, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->decimal('depreciated', 22, 2)->default(0);
            $table->foreignId('paid_from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('period', 7);                                  // 2026-11
            $table->date('date');
            $table->decimal('total', 22, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'period']);
        });

        Schema::create('depreciation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depreciation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_category_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('paid_account_id')->constrained('accounts')->restrictOnDelete(); // cash or bank, or a liability when it is not paid yet
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->decimal('base_amount', 22, 2);
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });

        Schema::create('liability_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();      // the liability
            $table->string('kind', 20);                                   // received (money came in, we owe it) or payment
            $table->date('date');
            $table->foreignId('money_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['liability_entries', 'expenses', 'depreciation_lines', 'depreciation_runs', 'fixed_assets', 'branch_fundings', 'capital_entries', 'shareholders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
