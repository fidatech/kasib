<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2, the central side.
 *  tenants.brand_name   white label: the name a business shows instead of the name of the system
 *  sellers              the people who sell the system for a share of what their clients pay
 *  commissions          what a seller earned on a payment, and the reward of a client who referred another
 *  linked_logins        users of different businesses that are the same person: "several businesses, one login"
 *  login_tokens         the pass that carries such a person from one business into another, good for a minute
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('email', 190)->nullable();
            $table->decimal('first_percent', 5, 2)->default(0);      // of what a client pays for its first year
            $table->decimal('renewal_percent', 5, 2)->default(0);    // of what it pays for the years after
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('brand_name', 150)->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('referred_by')->nullable()->constrained('tenants')->nullOnDelete();
        });
        Schema::table('signups', function (Blueprint $table) {
            $table->string('ref', 60)->nullable();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                              // seller, referral
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('referrer_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();        // the business that paid
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on')->nullable();                     // the day the company paid it out
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'paid_on']);
        });

        Schema::create('linked_logins', function (Blueprint $table) {
            $table->id();
            $table->char('group', 36)->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('email', 190);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('login_tokens', function (Blueprint $table) {
            $table->char('token', 64)->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_tokens');
        Schema::dropIfExists('linked_logins');
        Schema::dropIfExists('commissions');
        Schema::table('signups', fn (Blueprint $table) => $table->dropColumn('ref'));
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn('brand_name');
        });
        Schema::dropIfExists('sellers');
    }
};
