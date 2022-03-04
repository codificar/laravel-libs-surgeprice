<?php

namespace Codificar\SurgePrice\Console\Commands;

use Symfony\Component\Process\Process;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Requests;
use Provider;
use Codificar\SurgePrice\Models\SurgeSettings;
use Codificar\SurgePrice\Models\SurgeRegion;
use Codificar\SurgePrice\Models\SurgeCity;
use Codificar\SurgePrice\Models\SurgeArea;
use Codificar\SurgePrice\Models\SurgeHistory;
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
        file_put_contents($settings->model_files_path.DIRECTORY_SEPARATOR.$request_prediction_file, $inference);

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
        file_put_contents($settings->model_files_path.DIRECTORY_SEPARATOR.$provider_prediction_file, $inference);
        
        $noSurge = false;
        // No requests or providers in window, no surge to generate.
        if(count($requests) == 0 || count($providers) == 0)
            $noSurge = true;

        // Iterate over existing regions to predict the data.
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            // There are providers and requests in window, predict data.
            if($noSurge == false)
            {
                $provider_request_map = []; // supply and demand count
                $ml_path = $settings->model_files_path.DIRECTORY_SEPARATOR;
                // Empty request-output file.
                file_put_contents($ml_path.DIRECTORY_SEPARATOR.$region->state.'/request-output.csv', "");
                // Run the model prediction(inference) using python ML with the request data.
                $process = new Process(['python3', __DIR__.'/../../resources/scripts/predict-data.py',
                                        '-i',$request_prediction_file,
                                        '-d', 'request',
                                        '-s', $region->state,
                                        '-p' ,$ml_path]);
                $process->run();
                // Set the requests count in time period for each surge area in region.
                if (file_exists($ml_path.DIRECTORY_SEPARATOR.$region->state.'/request-output.csv'))
                {
                    $open = fopen($ml_path.DIRECTORY_SEPARATOR.$region->state.'/request-output.csv', "r");
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
                // Empty provider-output file.
                file_put_contents($ml_path.DIRECTORY_SEPARATOR.$region->state.'/provider-output.csv', "");
                // Run the model prediction(inference) using python ML with the provider data.
                $process = new Process(['python3', __DIR__.'/../../resources/scripts/predict-data.py',
                                        '-i',$provider_prediction_file,
                                        '-d', 'provider',
                                        '-s', $region->state,
                                        '-p' ,$ml_path]);
                $process->run();
                // Set the active providers count in time period for each surge area in region.
                if (file_exists($ml_path.DIRECTORY_SEPARATOR.$region->state.'/provider-output.csv')) 
                {
                    $open = fopen($ml_path.DIRECTORY_SEPARATOR.$region->state.'/provider-output.csv', "r");
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

                // Check if active providers are at least one per area.
                if($region->total_areas && count($providers) >= $region->total_areas)
                {
                    // Calculate means for region
                    $providers_avg = count($providers) / $region->total_areas;
                }
                // Not enough data in region to create surge areas, end surge for all areas if not finished.
                else
                    $noSurge = true;
                // Get average demand/supply for the entire region (including outliers).
                if($providers_avg)
                    $supply_demand_avg = count($requests) / count($providers);
                // Not enough providers in region, end surge for all areas if not finished.
                else
                    $noSurge = true;
            }

            // Add surge multiplier historical data for each surge area in region
            foreach ($region->surgeAreas()->get() as $surgeArea)
            {
                $surgeHistory = new SurgeHistory();
                $surgeHistory->surgeArea()->associate($surgeArea);
                // No surge, end surge for the area if not finished.
                if($noSurge)
                {
                    $surgeHistory->providers_count = 0;
                    $surgeHistory->requests_count = 0;
                    $this->endSurge($surgeHistory);
                }
                // Calculate new surge multiplier for the area.
                else if(array_key_exists($surgeArea->index, $provider_request_map))
                {
                    $surgeHistory->providers_count = $provider_request_map[$surgeArea->index][0];
                    $surgeHistory->requests_count = $provider_request_map[$surgeArea->index][1];
                    // area demand/supply
                    $factor =  $surgeHistory->requests_count /
                                // are there any providers in the surge area?
                                (($surgeHistory->providers_count)? $surgeHistory->providers_count:
                                // if not, use region average instead
                                $providers_avg);
                    
                    // Normalize factor by region average demand/supply.
                    if ($supply_demand_avg && $supply_demand_avg > 0)
                    {
                        $factor /= $supply_demand_avg;
                    }
                    // No average demand/supply = no data, no surge for area.
                    else
                    {
                        $factor = 1;
                    }

                    // Supply attends demand? End surge for area if not finished.
                    if($factor < $settings->min_surge)
                    {
                        $this->endSurge($surgeHistory);
                    }
                    // Set factor delimiters to create surge multiplier
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
                        $surgeHistory->multiplier = $factor;
                        // Save the current surge multiplier for the surge area.
                        $surgeHistory->save();
                    }
                }
            }
        }
    }

    // End surge: save a 1.0x multiplier only if the last one is a larger value.
    private function endSurge($newSurgeHistory)
    {
        $newSurgeHistory->multiplier = 1.0;
        // Get last history for area.
        $lastSurgeHistory = $newSurgeHistory->surgeArea->surgeHistory()->orderBy('created_at', 'DESC')->first();
        // Is the last history a surge for the area?
        if(!$lastSurgeHistory || ($lastSurgeHistory && $lastSurgeHistory->multiplier > 1))
        {
            // Save end of surge.
            $newSurgeHistory->save();
        }
    }
}
