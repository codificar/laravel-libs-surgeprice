<?php
namespace Codificar\SurgePrice;

use Illuminate\Support\ServiceProvider;

class SurgePriceServiceProvider extends ServiceProvider
{

    public function boot()
    {


        // Load routes (carrega as rotas)
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');

        // // Load laravel views (Carregas as views do Laravel, blade)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'surgeprice');

        // Load Migrations (Carrega todas as migrations)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // // Commands (Carrega os comandos do projeto)
        //$this->commands([Polling::class]);

        // // Load trans files (Carrega tos arquivos de traducao) 
        //$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'surgeprice');

        // Load seeds
        $this->publishes([
            __DIR__.'/Database/Seeds' => database_path('seeds')
        ], 'public_vuejs_libs');



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
        // return [
        //     'command.events.polling',
        // ];
    }
}
