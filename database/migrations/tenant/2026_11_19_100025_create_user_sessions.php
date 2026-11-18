<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where each user is signed in: one row for every browser, so a user can see them and sign the others out. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token', 64)->unique();                    // the fingerprint of the session, never the session itself
            $table->string('ip', 45)->nullable();
            $table->string('agent', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
