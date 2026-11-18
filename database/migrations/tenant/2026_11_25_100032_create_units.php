<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The units items are counted in: a list of its own, which the business adds to and removes from. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('name_local', 60)->nullable();
            $table->timestamps();
        });
        $now = now();
        $rows = [];
        foreach (\App\Models\Unit::DEFAULTS as $name => $local) {
            $rows[$name] = ['name' => $name, 'name_local' => $local, 'created_at' => $now, 'updated_at' => $now];
        }
        // what the items of the business are already counted in stays on the list
        foreach (DB::table('items')->distinct()->pluck('unit') as $used) {
            if (filled($used) && ! isset($rows[$used])) {
                $rows[$used] = ['name' => $used, 'name_local' => null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('units')->insert(array_values($rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
