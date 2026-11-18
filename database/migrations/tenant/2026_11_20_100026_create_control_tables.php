<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  alerts                 what the owner should know about: a very large paper, one dated back, one
 *                         made outside working hours, one cancelled, a budget nearly used up
 *  balance_confirmations  the balance a party was sent on WhatsApp, and what the party answered
 *  budgets                what an expense category may cost in a month
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('text', 255);
            $table->nullableMorphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kind', 'created_at']);
        });

        Schema::create('balance_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('balance', 255);
            $table->string('status', 12)->default('sent');          // sent, confirmed, disputed
            $table->string('note', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['party_id', 'sent_at']);
            $table->index('status');
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_category_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 22, 2);                       // for one month, in the base currency
            $table->string('warned_month', 10)->nullable();         // the month the owner was last warned in
            $table->unsignedTinyInteger('warned_level')->default(0);   // 80 or 100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('balance_confirmations');
        Schema::dropIfExists('alerts');
    }
};
