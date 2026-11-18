<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Version 2: keys another system (SarafBook) connects with, and the questions put to the assistant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('prefix', 12);                         // the first letters, so a key can be told from another
            $table->string('token', 64)->unique();                // sha256 of the key: the key itself is shown once and not kept
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('can_write')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->index();   // the number of the paper in the other system
        });
        Schema::create('assistant_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_questions');
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('reference'));
        Schema::dropIfExists('api_keys');
    }
};
