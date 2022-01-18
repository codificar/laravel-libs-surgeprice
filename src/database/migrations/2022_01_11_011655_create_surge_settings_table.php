<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Codificar\SurgePrice\Models\SurgeSettings;
use Carbon\Carbon;

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
        // Seed default settings.
        DB::table('surge_settings')->insert([
            'update_surge_window' => 5,
            'min_surge' => 1.5,
            'max_surge' => 5,
            'delimiter' => SurgeSettings::DAMPING,
            'lof_neighbors' => 15,
            'lof_contamination' => 0.05,
            'model_files_path' => '/var/tmp/surgeprice/',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
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
