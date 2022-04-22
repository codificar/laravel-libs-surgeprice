<?php

use Illuminate\Database\Migrations\Migration;

use Codificar\SurgePrice\Models\SurgeSettings;

class UpdateSurgeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $surgeSettings = SurgeSettings::first();

        $surgeSettings->model_files_path = 'surgeprice';
        $surgeSettings->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $surgeSettings = SurgeSettings::first();

        $surgeSettings->model_files_path = '/var/tmp/surgeprice/';
        $surgeSettings->save();
    }
}
