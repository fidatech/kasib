<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2, the Pro plan: sales and purchases.
 *  items          what the business buys and sells; the stock and its average cost are kept on the item
 *  invoices       sales, purchases and their returns
 *  invoice_lines  the items of an invoice
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 60)->nullable()->unique();
            $table->string('unit', 30)->default('pcs');
            $table->decimal('sale_price', 22, 2)->default(0);        // both prices in the base currency
            $table->decimal('cost_price', 22, 2)->default(0);
            $table->boolean('track_stock')->default(true);           // off: a service, nothing is counted
            $table->decimal('stock', 18, 3)->default(0);
            $table->decimal('avg_cost', 22, 4)->default(0);          // average cost of one unit in stock, base currency
            $table->decimal('reorder_level', 18, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('kind', 20);                              // sale, purchase, sale_return, purchase_return
            $table->date('date');
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 22, 2);
            $table->decimal('discount', 22, 2)->default(0);
            $table->decimal('total', 22, 2);
            $table->decimal('base_total', 22, 2);                    // the total in the base currency on that day
            $table->decimal('cost_total', 22, 2)->default(0);        // what the goods cost (sales) or are worth in stock (purchases), base currency
            $table->decimal('paid', 22, 2)->default(0);              // paid on the spot
            $table->foreignId('payment_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['kind', 'date']);
            $table->index(['party_id', 'date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('qty', 18, 3);
            $table->decimal('price', 22, 2);
            $table->decimal('amount', 22, 2);
            $table->decimal('base_amount', 22, 2)->default(0);       // its share of the total, after the discount, base currency
            $table->decimal('base_cost', 22, 2)->default(0);         // the cost that left or entered the stock with it, base currency
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('items');
    }
};
