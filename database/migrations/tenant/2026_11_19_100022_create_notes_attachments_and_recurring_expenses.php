<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  party_notes         what was said with a customer or a supplier ("he will pay on Thursday"), with a day to be reminded
 *  attachments         a photo or a PDF kept with an account or with a paper
 *  recurring_expenses  expenses that come every month (rent, salaries, internet); the system records them by itself
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('note', 500);
            $table->date('remind_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['done_at', 'remind_on']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);                     // party, voucher
            $table->unsignedBigInteger('subject_id');
            $table->string('path', 255);
            $table->string('name', 200);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('paid_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->unsignedTinyInteger('day')->default(1);          // day of the month, 1 to 28
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('last_posted_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('party_notes');
    }
};
