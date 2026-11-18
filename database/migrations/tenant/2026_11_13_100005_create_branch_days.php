<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The working day of a branch: opened with the rates of the day, closed and locked at midnight. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 10)->default('open');               // open, closed
            // opened by the system for a paper of another branch; the branch's own people still confirm the rates
            $table->boolean('auto_opened')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_total', 22, 2)->default(0);          // cash and bank of the branch in AFN, at the opening rates
            $table->decimal('closing_total', 22, 2)->nullable();
            $table->decimal('revaluation', 22, 2)->default(0);            // gain (+) or loss (-) posted to other income at opening
            $table->timestamps();
            $table->unique(['branch_id', 'date']);
        });

        Schema::create('branch_day_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('opening', 22, 2)->default(0);
            $table->decimal('opening_rate', 18, 6)->nullable();
            $table->decimal('closing', 22, 2)->nullable();
            $table->decimal('closing_rate', 18, 6)->nullable();
            $table->unique(['branch_day_id', 'currency_id']);
        });

        // an entry of a closed day is never changed: a second entry on a later day takes it back
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['reversal_of_id', 'reversed_by_id']);
        });
        Schema::dropIfExists('branch_day_balances');
        Schema::dropIfExists('branch_days');
    }
};
