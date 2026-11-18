<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version 2: branches, each with books of its own.
 *  The papers that had no branch get one (the main branch for what exists).
 *  item_stocks       the stock and the average cost of an item, branch by branch
 *  branch_transfers  money sent from one branch to another; it is in the books of both
 */
return new class extends Migration
{
    private const TABLES = ['invoices', 'hawalas', 'handovers', 'deals', 'recurring_expenses'];

    public function up(): void
    {
        $main = DB::table('branches')->where('type', 'main')->value('id');
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            });
            if ($main) {
                DB::table($name)->update(['branch_id' => $main]);
            }
        }

        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('stock', 18, 3)->default(0);
            $table->decimal('avg_cost', 22, 4)->default(0);
            $table->unique(['item_id', 'branch_id']);
        });
        if ($main) {
            foreach (DB::table('items')->get(['id', 'stock', 'avg_cost']) as $item) {
                DB::table('item_stocks')->insert(['item_id' => $item->id, 'branch_id' => $main, 'stock' => $item->stock, 'avg_cost' => $item->avg_cost]);
            }
        }
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['stock', 'avg_cost']);
        });

        Schema::create('branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 22, 2);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfers');
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('stock', 18, 3)->default(0);
            $table->decimal('avg_cost', 22, 4)->default(0);
        });
        Schema::dropIfExists('item_stocks');
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        }
    }
};
