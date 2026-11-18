<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business paid its subscription to the HesabPay number of the company and says so from inside
 * the system: the amount, the number of the transaction and a picture of it. The administrator
 * approves it (the payment is recorded and the licence moves on) or rejects it with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reference', 120);
            $table->string('screenshot_path', 255)->nullable();
            $table->string('sender', 150)->nullable();               // the user of the business who sent it
            $table->string('status', 10)->default('pending');        // pending, approved, rejected
            $table->string('reason', 40)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
