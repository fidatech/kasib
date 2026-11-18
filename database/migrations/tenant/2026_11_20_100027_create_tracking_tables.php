<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  hawalas        money sent through a saraf, followed by its number until it is delivered
 *  cleared_lines  the lines of a bank account that were found on the statement of the bank
 *  handovers      a cashier counts his box and hands it to another box at the end of his shift
 *  rate_alerts    "tell me when the dollar goes above 71"
 *  parties.user_id  the user who holds a cash box
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hawalas', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('number', 60);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();      // the saraf (or bank) it was sent through
            $table->decimal('amount', 22, 2);
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();       // on whose behalf
            $table->string('receiver', 150);
            $table->string('receiver_phone', 50)->nullable();
            $table->string('destination', 150)->nullable();
            $table->string('status', 12)->default('sent');            // sent, delivered, cancelled
            $table->date('delivered_on')->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'date']);
            $table->index('number');
        });

        Schema::create('cleared_lines', function (Blueprint $table) {
            $table->foreignId('journal_line_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cleared_at')->useCurrent();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('from_party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('to_party_id')->constrained('parties')->restrictOnDelete();
            $table->json('lines');                                    // [{currency, book, counted, difference}]
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rate_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 6);                           // above, below
            $table->decimal('rate', 18, 6);
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('fired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_alerts');
        Schema::dropIfExists('handovers');
        Schema::table('parties', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('cleared_lines');
        Schema::dropIfExists('hawalas');
    }
};
