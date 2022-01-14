<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurgeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surge_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('update_surge_window'); // minutes
            $table->float('min_surge');
            $table->float('max_surge');
            $table->enum('delimiter', ['NONE', 'PRUNE', 'DAMPING'])->default('DAMPING');
            $table->unsignedTinyInteger('lof_neighbors');
            $table->float('lof_contamination');
            $table->string('model_files_path', 4096);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('surge_settings');
    }
}
