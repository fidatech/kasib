<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  deals        something bought to be sold again, kept only as money: what was paid for it, what
 *               came in from it, and the profit or loss when it is closed.
 *  cash_counts  the cash counted by hand, set against what the books say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('name', 150);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();   // what the deal holds, in the base currency
            $table->string('status', 10)->default('open');          // open, closed
            $table->date('opened_on');
            $table->date('closed_on')->nullable();
            $table->decimal('result', 22, 2)->nullable();           // profit (or loss, below zero), written when it is closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->after('party_id')->constrained()->restrictOnDelete();
        });

        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('book', 22, 2);                         // what the books said
            $table->decimal('counted', 22, 2);                      // what was in the box
            $table->decimal('difference', 22, 2);                   // counted minus book: above zero is an excess
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deal_id');
        });
        Schema::dropIfExists('deals');
    }
};
