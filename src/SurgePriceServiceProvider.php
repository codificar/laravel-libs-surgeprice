<?php
namespace Codificar\SurgePrice;

use Illuminate\Support\ServiceProvider;
use Codificar\SurgePrice\Console\Commands\TrainModels;
use Codificar\SurgePrice\Console\Commands\PredictData;

class SurgePriceServiceProvider extends ServiceProvider
{

    public function boot()
    {


        // Load routes (carrega as rotas)
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        // // Load laravel views (Carregas as views do Laravel, blade)
        $this->loadViewsFrom(__DIR__.'/resources/views', 'surgeprice');

        // Load Migrations (Carrega todas as migrations)
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // // Commands (Carrega os comandos do projeto)
        $this->commands([TrainModels::class, PredictData::class]);

        // // Load trans files (Carrega tos arquivos de traducao) 
        //$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'surgeprice');

        // Load seeds
        $this->publishes([
            __DIR__.'/database/seeds' => database_path('seeds')
        ], 'surgeprice-seeds');



    }

    public function register()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.ml.train_models',
            'command.ml.predict_data',
        ];
    }
}
