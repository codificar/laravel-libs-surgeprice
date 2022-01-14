<?php

namespace Codificar\SurgePrice\Console\Commands;

use Symfony\Component\Process\Process;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Requests;
use Provider;
use Codificar\Surgeprice\Models\SurgeSettings;
use Codificar\Surgeprice\Models\SurgeRegion;
use Codificar\Surgeprice\Models\SurgeCity;
use Codificar\Surgeprice\Models\SurgeArea;
use Codificar\Surgeprice\Models\SurgeHistory;
use DB;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Predict surge areas multipliers given recent request and provider data.
 * 
 * This command updates the surge multiplier in each area, given a defined time period, using the last provider activities
 * and the requests in the same period (supply and demmand). It is recommended to execute this command in the
 * same periodicity as the defined time period.
 */
class PredictData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ml:predict_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Predict surge areas multipliers given recent request and provider data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $settings = SurgeSettings::first();
        $begin = Carbon::now()->subMinutes($settings->update_surge_window);
        $end = Carbon::now();
        // Iterate over requests in the time period.
        $request_prediction = [];
        $requests = Requests::where('created_at', '>=', $begin)
        ->where('created_at', '<=', $end)
        ->orderBy('created_at')
        ->get();
        foreach($requests as $i => $request)
        {
            // Add the request data to the prediction list.
            $request_prediction []= implode(',', [$request->latitude, $request->longitude, $request->id]);
        }
        $request_prediction_file = 'request-predict.csv';
        $inference = implode(PHP_EOL,$request_prediction);
        file_put_contents($settings->model_files_path.'/'.$request_prediction_file, $inference);

        // Iterate over active providers in the time period.
        $provider_prediction = [];
        $providers = Provider::where('is_available', true)
        ->where('is_active', true)
        ->where('last_activity', '>=', $begin)
        ->where('last_activity', '<=', $end)
        ->orderBy('last_activity')
        ->get();
        foreach($providers as $i => $provider)
        {
            // Add the provider data to the prediction list.
            $provider_prediction []= implode(',', [$provider->latitude, $provider->longitude, $provider->id]);
        }
        $provider_prediction_file = 'provider-predict.csv';
        $inference = implode(PHP_EOL,$provider_prediction);
        file_put_contents($settings->model_files_path.'/'.$provider_prediction_file, $inference);
        
        // Iterate over existing regions to predict the data.
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $provider_request_map = []; // supply and demand count
            $ml_path = $settings->model_files_path.'/';
            // Run the model prediction(inference) using python ML with the request data.
            $process = new Process(['python', __DIR__.'/../resources/scripts/predict-data.py',
                                    '-i',$request_prediction_file,
                                    '-d', 'request',
                                    '-s', $region->state,
                                    '-p' ,$ml_path]);
            $process->run();
            // Set the requests count in time period for each surge area in region.
            if (($open = fopen($ml_path.'/'.$region->state.'/request-output.csv', "r")) !== FALSE) 
            {
                while (($line = fgets($open)) !== false) {
                    $area = array_map('intval', explode(",",$line))[0];
                    if(!array_key_exists($area, $provider_request_map))
                    {
                        $provider_request_map[$area] = [0,1];
                    }
                    else
                    {
                        $provider_request_map[$area][1] += 1;
                    }
                }            
                fclose($open);
            }
            // Run the model prediction(inference) using python ML with the provider data.
            $process = new Process(['python', __DIR__.'/../resources/scripts/predict-data.py',
                                    '-i',$provider_prediction_file,
                                    '-d', 'provider',
                                    '-s', $region->state,
                                    '-p' ,$ml_path]);
            $process->run();
            // Set the active providers count in time period for each surge area in region.
            if (($open = fopen($ml_path.'/'.$region->state.'/provider-output.csv', "r")) !== FALSE) 
            {
                while (($line = fgets($open)) !== false) {
                    $area = array_map('intval', explode(",",$line))[0];
                    if(!array_key_exists($area, $provider_request_map))
                    {
                        $provider_request_map[$area] = [1,0];
                    }
                    else
                    {
                        $provider_request_map[$area][0] += 1;
                    }
                }
                fclose($open);
            }

            // Add surge multiplier historical data for each surge area in region
            foreach ($region->surgeAreas()->get() as $surgeArea)
            {
                if(array_key_exists($surgeArea->index, $provider_request_map))
                {
                    $surgeHistory = new SurgeHistory();
                    $surgeHistory->surgeArea()->associate($surgeArea);
                    $surgeHistory->providers_count = $provider_request_map[$surgeArea->index][0];
                    $surgeHistory->requests_count = $provider_request_map[$surgeArea->index][1];
                    $factor =  $surgeHistory->requests_count /  $surgeHistory->providers_count; // supply/demand

                    // Set multiplier delimiters
                    if($factor < $settings->min_surge)
                    {
                        // No surge multiplier, use default value
                        $factor = 1.0;
                    }
                    else
                    {
                        // Set upper bound for multiplier given the delimiter method.
                        switch ($settings->delimiter)
                        {
                            case SurgeSettings::DAMPING:
                                // MIN + (MAX * log10(factor/MIN))
                                $factor = $settings->min_surge + ($settings->max_surge * log10($factor/$settings->min_surge));
                                break;
                            case SurgeSettings::PRUNE:
                                // MIN <= factor <= MAX
                                $factor = min($settings->max_surge, $factor);
                                break;
                            case SurgeSettings::NONE:
                            default:
                                break;
                        }
                    }
                    $surgeHistory->multiplier = $factor;
                    
                    // Save the current surge multiplier for the surge area.
                    $surgeHistory->save();
                }
            }
        }
    }
}
