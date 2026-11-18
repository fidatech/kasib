<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A period of the books, closed for the whole business at once. Hawala numbers start from 1 again in the next one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->date('started_on')->nullable();
            $table->date('closed_on');
            $table->json('branches');                                  // [{branch_id, name, profit, to}] what each branch made and where it went
            $table->unsignedInteger('remittances')->default(0);        // hawala numbers used in the period
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
