<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurgeRegionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surge_regions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country', 100);
            $table->enum('state', ['AC','AL','AP','AM','BA','CE','DF',
                                    'ES','GO','MA','MT','MS','MG','PA',
                                    'PB','PR','PE','PI','RJ','RN','RS',
                                    'RO','RR','SC','SP','SE','TO']);
            $table->enum('area_size', ['S', 'M', 'L']);
            $table->unsignedSmallInteger('min_area_requests'); // count for training list prune
            $table->unsignedInteger('total_areas')->default(0); // current area count calculated via model training
            $table->timestamps();

            $table->unique(['state']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surge_regions', function (Blueprint $table) {
            $table->dropUnique(['state']);
        });
        Schema::dropIfExists('surge_regions');
    }
}
