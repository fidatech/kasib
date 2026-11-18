<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  parties.share_token  the private link a customer opens to read his own statement
 *  documents            the document vault: licences, contracts, identity papers of the business
 *  report_schedules     an Excel workbook sent by email every day, week or month
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('share_token', 48)->nullable()->unique();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('category', 20)->default('other');
            $table->date('expires_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('path', 255);
            $table->string('name', 200);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('warned_at')->nullable();              // the owner was told it is about to expire
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('expires_on');
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('content', 20)->default('all');           // which sheets: all, papers, statements, accounts
            $table->string('frequency', 10)->default('daily');       // daily, weekly, monthly
            $table->unsignedTinyInteger('day')->default(1);          // weekly: 1 Saturday … 7 Friday; monthly: day 1 to 28
            $table->unsignedTinyInteger('hour')->default(20);
            $table->string('email', 190);
            $table->boolean('is_active')->default(true);
            $table->date('last_sent_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('documents');
        Schema::table('parties', fn (Blueprint $table) => $table->dropColumn('share_token'));
    }
};
