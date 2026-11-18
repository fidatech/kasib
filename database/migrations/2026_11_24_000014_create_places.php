<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The provinces of Afghanistan and their cities, for the registration form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('name_fa', 100);
            $table->string('name_ps', 100);
            $table->unsignedSmallInteger('sort')->default(0);
        });
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('name_local', 100);
            $table->boolean('is_centre')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unique(['province_id', 'name']);
        });
        Schema::table('signups', fn (Blueprint $table) => $table->string('city', 100)->nullable()->after('province'));

        $sort = 0;
        foreach (require database_path('data/afghanistan.php') as $province => $cities) {
            [$name, $fa, $ps] = explode('|', $province);
            $id = DB::table('provinces')->insertGetId(['name' => $name, 'name_fa' => $fa, 'name_ps' => $ps, 'sort' => ++$sort]);
            $rows = [];
            foreach ($cities as $index => $city) {
                [$cityName, $local] = explode('|', $city);
                $rows[] = ['province_id' => $id, 'name' => $cityName, 'name_local' => $local, 'is_centre' => $index === 0, 'sort' => $index + 1];
            }
            DB::table('cities')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('signups', fn (Blueprint $table) => $table->dropColumn('city'));
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
