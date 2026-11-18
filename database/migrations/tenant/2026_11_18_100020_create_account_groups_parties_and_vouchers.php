<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the people of a business work with every day.
 *
 *  account_groups  the "account categories" of the menu: Cash, Bank, Saraf, Customers, Suppliers and
 *                  any the business adds. Each one is a page of its own under Accounts.
 *  parties         the accounts of a category as people know them: a cash box, a bank account, a
 *                  customer. A party has one ledger account for every currency it uses.
 *  vouchers        money in, money out, transfers, exchanges and income; each posts one journal entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();                    // part of the address: /accounts/customers
            $table->string('name', 150);
            $table->string('name_fa', 150)->nullable();
            $table->string('name_ps', 150)->nullable();
            $table->string('type', 10);                             // money: the business's own funds; party: someone it owes or is owed by
            $table->string('icon', 40)->default('address-book');
            $table->foreignId('account_category_id')->constrained()->restrictOnDelete();   // its group in the chart of accounts
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_group_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_system')->default(false);           // the cash box every business starts with
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['account_group_id', 'name']);
            $table->index('phone');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('party_id')->nullable()->after('account_category_id')->constrained()->restrictOnDelete();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('kind', 20);                             // money_in, money_out, transfer, exchange, income
            $table->date('date');
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();              // the money account: received into, or paid from
            $table->foreignId('other_account_id')->constrained('accounts')->restrictOnDelete();        // the party, the second money account, or the income account
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->foreignId('other_currency_id')->nullable()->constrained('currencies')->restrictOnDelete();   // an exchange: the currency received
            $table->decimal('other_amount', 22, 2)->nullable();
            $table->decimal('rate', 18, 6)->nullable();             // an exchange: units received for one unit given
            $table->decimal('fee', 22, 2)->default(0);              // a transfer: what the bank or the saraf took
            $table->decimal('base_amount', 22, 2)->default(0);      // the value in the base currency on that day
            $table->decimal('profit', 22, 2)->default(0);           // an exchange: gain (or loss) against the rate of the business
            $table->date('due_date')->nullable();                   // money in: the day it is to be paid back
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['kind', 'date']);
            $table->index(['party_id', 'date']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('party_id');
        });
        Schema::dropIfExists('parties');
        Schema::dropIfExists('account_groups');
    }
};
