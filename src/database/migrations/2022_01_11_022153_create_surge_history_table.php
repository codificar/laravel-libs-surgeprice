<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurgeHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surge_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('surge_area_id');
            $table->unsignedInteger('providers_count')->default(0); // supply
            $table->unsignedInteger('requests_count')->default(0); // demand
            $table->float('multiplier')->default(1); // surge area multiplier
            $table->timestamps();

            $table->foreign('surge_area_id')->references('id')->on('surge_areas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surge_history', function (Blueprint $table) {
            $table->dropForeign(['surge_area_id']);
        });
        Schema::dropIfExists('surge_history');
    }
}
