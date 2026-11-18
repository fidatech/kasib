<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fingerprint of every backup the system made. A backup file that is uploaded to be put
 * back is accepted only when its fingerprint is here, under the same business: the system
 * restores its own files and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_seals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tenant_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_seals');
    }
};
