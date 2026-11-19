<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for books with hundreds of thousands of papers: the lists of a branch by kind and by day. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->index(['branch_id', 'kind', 'id']);
            $table->index(['branch_id', 'date']);
        });
        Schema::table('journal_entries', fn (Blueprint $table) => $table->index(['branch_id', 'type']));
        Schema::table('journal_lines', fn (Blueprint $table) => $table->index(['journal_entry_id', 'account_id']));
    }

    public function down(): void
    {
        Schema::table('journal_lines', fn (Blueprint $table) => $table->dropIndex(['journal_entry_id', 'account_id']));
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropIndex(['branch_id', 'type']));
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'date']);
            $table->dropIndex(['branch_id', 'kind', 'id']);
        });
    }
};
