<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses: the stock of an item is kept warehouse by warehouse inside a branch, and goods move
 * between them. Purchase costs: customs, transport, rent and the like, paid on a purchase and
 * carried into the cost of what was bought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('address', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'name']);
        });
        $now = now();
        $main = [];
        foreach (DB::table('branches')->pluck('id') as $branch) {
            $main[$branch] = DB::table('warehouses')->insertGetId(['branch_id' => $branch, 'name' => 'Main warehouse', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
        });
        foreach ($main as $branch => $warehouse) {
            DB::table('item_stocks')->where('branch_id', $branch)->update(['warehouse_id' => $warehouse]);
        }
        // the new key first: the old one also serves the link to the item, which may not be left without an index
        Schema::table('item_stocks', fn (Blueprint $table) => $table->unique(['item_id', 'warehouse_id']));
        Schema::table('item_stocks', fn (Blueprint $table) => $table->dropUnique(['item_id', 'branch_id']));

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('costs_total', 22, 2)->default(0);       // the costs of a purchase, in the base currency
        });
        foreach ($main as $branch => $warehouse) {
            DB::table('invoices')->where('branch_id', $branch)->update(['warehouse_id' => $warehouse]);
        }
        Schema::create('invoice_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                             // customs, transport, rent ...
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();   // the money account it was paid from
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->decimal('base_amount', 22, 2);
        });
        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('qty', 18, 3);
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('invoice_costs');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('costs_total');
        });
        Schema::table('item_stocks', fn (Blueprint $table) => $table->unique(['item_id', 'branch_id']));
        Schema::table('item_stocks', fn (Blueprint $table) => $table->dropUnique(['item_id', 'warehouse_id']));
        Schema::table('item_stocks', fn (Blueprint $table) => $table->dropConstrainedForeignId('warehouse_id'));
        Schema::dropIfExists('warehouses');
    }
};
