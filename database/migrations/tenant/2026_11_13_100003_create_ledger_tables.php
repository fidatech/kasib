<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Currencies, rates, the chart of accounts and the journal every operation posts into. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            $table->string('name_local', 100)->nullable();
            $table->string('symbol', 10)->nullable();
            $table->unsignedInteger('quote_unit')->default(1);   // rates are written for 1, 100 or 1,000 units
            $table->boolean('is_base')->default(false);          // AFN: reports are in this currency
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        // rate = how many AFN one quote unit of the currency is worth, in this branch. The newest row counts.
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 18, 6);
            $table->decimal('buy_rate', 18, 6)->nullable();
            $table->decimal('sell_rate', 18, 6)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'currency_id', 'id']);
        });

        // groups of the chart of accounts; each points at one line of a DAB statement
        Schema::create('account_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('nature', 10);                         // asset, liability, equity, income, expense
            $table->string('dab_line', 40)->nullable();           // a key of config/dab.php
            $table->boolean('is_system')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        // an account belongs to one branch and holds one currency
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name', 150);
            $table->string('name_local', 150)->nullable();
            $table->string('system_key', 50)->nullable();          // cash, exchange_income ...: accounts the system opens itself
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'system_key', 'currency_id']);
            $table->index(['branch_id', 'account_category_id']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->string('type', 40)->index();
            $table->string('description', 500)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });

        // every line twice: in the currency of its account and as its value in AFN on that day
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('debit', 22, 2)->default(0);
            $table->decimal('credit', 22, 2)->default(0);
            $table->decimal('base_debit', 22, 2)->default(0);
            $table->decimal('base_credit', 22, 2)->default(0);
            $table->string('memo', 500)->nullable();
            $table->boolean('is_void')->default(false);
            $table->index(['account_id', 'date']);
            $table->index(['branch_id', 'date']);
        });

        // kept up to date in the same transaction as the lines, so no page adds up the whole history
        Schema::create('account_balances', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 22, 2)->default(0);        // debits minus credits, own currency
            $table->decimal('base_balance', 22, 2)->default(0);   // the same in AFN, at the rates of each day
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['account_balances', 'journal_lines', 'journal_entries', 'accounts', 'account_categories', 'exchange_rates', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
