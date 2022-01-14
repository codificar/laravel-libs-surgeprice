<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurgeAreasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surge_areas', function (Blueprint $table) {
            $table->increments('id'); // ignore
            $table->unsignedInteger('region_id');
            $table->unsignedInteger('index');
            $table->point('centroid');
            // $table->unsignedInteger('providers_count')->default(0); // supply
            // $table->unsignedInteger('requests_count')->default(0); // demand
            // $table->float('mutiplier')->default(1); // current surge area multiplier

            $table->index('index');
            $table->spatialIndex('centroid');

            $table->unique(['region_id', 'index']); // composite key region[index]

            $table->foreign('region_id')->references('id')->on('surge_regions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surge_areas', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropIndex(['index']);
            $table->dropSpatialIndex(['centroid']);

            $table->dropUnique(['region_id', 'index']);
        });
        Schema::dropIfExists('surge_areas');
    }
}
