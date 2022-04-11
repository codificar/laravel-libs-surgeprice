<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterSurgeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('surge_settings', function (Blueprint $table) {
            $table->float('heatmap_expand_factor')->default(1);
            $table->json('heatmap_colors');
            $table->json('heatmap_colors_pos');
        });

        DB::table('surge_settings')
            ->where('id', 1)
            ->update(['heatmap_colors' => '["#5F99D8", "#60E0A8", "#FBB021", "#F68838", "#EE3E32"]']);
        DB::table('surge_settings')
            ->where('id', 1)
            ->update(['heatmap_colors_pos' => '[0.1, 0.35, 0.65, 0.85, 1]']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surge_settings', function (Blueprint $table) {
            $table->dropColumn('heatmap_expand_factor');
            $table->dropColumn('heatmap_colors');
            $table->dropColumn('heatmap_colors_pos');
        });
    }
}
