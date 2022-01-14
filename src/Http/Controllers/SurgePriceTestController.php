<?php

namespace Codificar\SurgePrice\Http\Controllers;

use Illuminate\Http\Request;

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

class SurgePriceTestController extends Controller
{

    public function index()
    {
        
    }
    /**
     * # MODEL TRAIN LIST GENERATION SCRIPT
     * 
     * This script changes the model training list, and consequently the cluster (surge) areas positions.
     * As it may change the geographic configuration of the surge areas,
     * it must be executed once per month at least, on new regions,
     * or over a longer period in established regions,
     * in order to keep a reliable surge historic.
     * 
     **/
    public function trainModel()
    {
        $trainedData = [];
        $prefixesLentgh = [];
        $regionCities = [];
        $currentRegions = [];
        
        // Get all regions
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $currentRegions[$region->state] = $region;
            $trainedData[$region->state] = [];
            // Set prefix length to calculate total cluster areas
            switch ($region->area_size) {
                case 'L':
                    # Large 3 digits prefix
                    $prefixesLentgh[$region->state] = 3;
                    break;
                case 'M':
                    # Medium 4 digits prefix
                    $prefixesLentgh[$region->state] = 4;
                    break;
                default:
                    # Small 5 digits prefix
                    $prefixesLentgh[$region->state] = 5;
                    break;
            }
            // get region cities into set
            $regionCities[$region->state] = $region->cities()->pluck('enabled','name')->toArray();
        }

        
        // # CREATE TRAINING LISTS (ONCE PER REGION)
        // # ADD NEW CITIES TO EXISTING REGIONS
        // Iterate over requests between yesterday and 1 year ago
        $end = Carbon::now()->subDays(1);
        $begin = Carbon::now()->subYears(1);
        $requests = Requests::where('created_at', '>=', $begin)
        ->where('created_at', '<=', $end)
        ->orderBy('created_at')
        ->get();
        foreach($requests as $i => $request)
        {
            // split request address to get needed info
            $split_addr = explode(",", $request->src_address);
            //add country to search address
            $country = trim(array_pop($split_addr));
            // it is a valid postal code?
            $postal_code = trim(array_pop($split_addr));
            if(preg_match('/[0-9]{5}-[0-9]{3}/', $postal_code))
            {                
                // get state
                $cityState = explode("-", array_pop($split_addr));
                $state = trim(array_pop($cityState));
                // Is request from a valid region?
                if(array_key_exists($state, $currentRegions))
                {
                    // get postal code prefix for request
                    $prefix = substr($postal_code, 0, $prefixesLentgh[$state]);
                    // GET REQUEST CITY
                    if(count($cityState) > 0)
                        $city = trim(array_pop($cityState));
                    else
                    {
                        $suffixCity = explode("-", array_pop($split_addr));
                        $city = trim(array_pop($suffixCity));
                    }
                    // IF CITY DONT EXISTS IN THE REGION
                    if(!array_key_exists($city, $regionCities[$state]) &&
                    // AND IS A VALID CITY NAME
                    !preg_match('~[0-9]+~', $city) && 
                    preg_match('~^\p{Lu}~u', $city)
                    )
                    {
                        // PUT CITY ON REGION AS ENABLED
                        $newCity = new SurgeCity();
                        $newCity->name = $city;
                        $newCity->region()->associate($currentRegions[$state]);
                        $newCity->save();
                        $regionCities[$state][$city] = true;
                    }
                    // IF CITY EXISTS IN A REGION AND IT IS ENABLED
                    if(array_key_exists($city, $regionCities[$state]) && $regionCities[$state][$city])
                    {
                        // ADD REQUEST POSITION TO REGION MODEL TRAINING LIST (LAT, LNG, PREFIX)
                        $trainedData[$state] []= implode(',', [$request->latitude, $request->longitude, $prefix]);
                    }
                }
            }
        }

        $settings = SurgeSettings::first();
        $train_file = 'request-train.csv';
        // FOR EACH REGION IN SET:
        foreach ($trainedData as $state => $regionTrainedData)
        {
            // create model files directory
            $full_path = $settings->model_files_path.'/'.$state.'/';
            if (!file_exists($full_path))
            {
                mkdir($full_path, 0777, true);
            }
            //  # SAVE TRAIN LIST FILE (PATH IN SETTINGS)
            $train = implode(PHP_EOL,$regionTrainedData);
            file_put_contents($full_path.$train_file, $train);
            //  # RUN MODEL TRAIN IN PYTHON MACHINE LEARNING TO GET TOTAL AREAS, CENTROIDS AND INDEXES
            //  PARAMETERS: train file, MIN AREA REQUEST COUNT, LOF NO NEIGHBORS, LOF CONTAMINATION, output path
            $process = new Process(['python', app_path().'/train-models.py',
                                    '-t',$train_file,
                                    '-m', $currentRegions[$state]->min_area_requests, 
                                    '-n', $settings->lof_neighbors, 
                                    '-c', $settings->lof_contamination,
                                    '-p' ,$full_path]);
            $process->run();
            if (($open = fopen($full_path.'/centroids.csv', "r")) !== FALSE) 
            {
            
              while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
              {        
                $centroids[] = array_map('floatval', $data); 
              }
            
              fclose($open);
            }
            //  # UPDATE TOTAL AREAS FOR REGION ON DB (READONLY IN CRUD)
            $currentRegions[$state]->total_areas = count($centroids);
            $currentRegions[$state]->save();
            $currentRegions[$state]->surgeAreas()->delete();
            //  # UPDATE CENTROIDS AND INDEXES FOR REGION IN DB
            foreach ($centroids as $i => $centroid)
            {
                $surgeArea = new SurgeArea();
                $surgeArea->region()->associate($currentRegions[$state]);
                $surgeArea->index = $i;
                $surgeArea->centroid = new Point($centroid[0], $centroid[1]);
                $surgeArea->save();
            }
        }

        // # TODO: CALL INFERENCE SCRIPT FOR LAST WINDOW?
    }

    /**
     * # DATA PREDICTION SCRIPT (RUNS IN THE SAME PERIOD AS RECOVERED WINDOW FROM DB)
     * 
     * This script updates the surge multiplier in each area, given a defined window, using the last provider activities
     * and the requests in the same period (supply and demmand). It is recommended to execute this command in the
     * same periodicity as the defined window.
     */
    public function dataInference()
    {
        $settings = SurgeSettings::first();
        //   FOR EACH REQUEST IN THE WINDOW PERIOD:
        $request_prediction = [];
        $begin = Carbon::now()->subMinutes($settings->update_surge_window);
        // TODO: REMOVER (begin1, begin2)
        $begin1 = Carbon::now()->subDays(104);
        $begin2 = Carbon::now()->subMinutes($settings->update_surge_window * 438000);
        $end = Carbon::now();
        // TODO: REMOVER begin1
        $requests = Requests::where('created_at', '>=', $begin1)
        ->where('created_at', '<=', $end)
        ->orderBy('created_at')
        ->get();
        foreach($requests as $i => $request)
        {
            //   ADD REQUEST TO REQUEST PREDICT LIST
            $request_prediction []= implode(',', [$request->latitude, $request->longitude, $request->id]);
        }
        $request_prediction_file = 'request-predict.csv';
        $inference = implode(PHP_EOL,$request_prediction);
        file_put_contents($settings->model_files_path.'/'.$request_prediction_file, $inference);

        //   FOR EACH PROVIDER IN THE WINDOW PERIOD:
        $provider_prediction = [];
        $providers = Provider::where('is_available', true)
        // TODO: ATIVAR IS ACTIVE
        // ->where('is_active', true)
        // TODO: REMOVER begin2
        ->where('last_activity', '>=', $begin2)
        ->where('last_activity', '<=', $end)
        ->orderBy('last_activity')
        ->get();
        foreach($providers as $i => $provider)
        {
            //   ADD PROVIDER TO PROVIDER PREDICT LIST
            $provider_prediction []= implode(',', [$provider->latitude, $provider->longitude, $provider->id]);
        }
        $provider_prediction_file = 'provider-predict.csv';
        $inference = implode(PHP_EOL,$provider_prediction);
        file_put_contents($settings->model_files_path.'/'.$provider_prediction_file, $inference);
        
        //  FOR EACH REGION MODEL (EACH TRAINING LIST)
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $provider_request_map = []; // supply and demand count
            $full_path = $settings->model_files_path.'/';
            //  RUN MODEL PREDICT IN PYTHON MACHINE LEARNING USING REQUEST PREDICT LIST
            //  PARAMETERS: prediction (inference) file, data type, region state, base path to model
            $process = new Process(['python', app_path().'/predict-data.py',
                                    '-i',$request_prediction_file,
                                    '-d', 'request',
                                    '-s', $region->state,
                                    '-p' ,$full_path]);
            $process->run();
            //  # UPDATE REQUESTS COUNT FOR EACH AREA IN REGION
            if (($open = fopen($full_path.'/'.$region->state.'/request-output.csv', "r")) !== FALSE) 
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
                // while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
                // {        
                //     $region_requests[] = array_map('intval', $data); 
                // }
            
                fclose($open);
            }
            //  RUN MODEL PREDICT IN PYTHON MACHINE LEARNING USING PROVIDER PREDICT LIST
            //  PARAMETERS: prediction (inference) file, data type, region state, base path to model
            $process = new Process(['python', app_path().'/predict-data.py',
                                    '-i',$provider_prediction_file,
                                    '-d', 'provider',
                                    '-s', $region->state,
                                    '-p' ,$full_path]);
            $process->run();
            //   # UPDATE PROVIDERS COUNT FOR EACH AREA IN REGION
            if (($open = fopen($full_path.'/'.$region->state.'/provider-output.csv', "r")) !== FALSE) 
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
                // while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
                // {        
                //     $region_providers[] = array_map('intval', $data); 
                // }
            
                fclose($open);
            }

            // ITERATE OVER EACH SURGE AREA IN REGION TO CREATE SURGE DATA ENTRY, IF EXISTS
            foreach ($region->surgeAreas()->get() as $surgeArea)
            {
                if(array_key_exists($surgeArea->index, $provider_request_map))
                {
                    $surgeHistory = new SurgeHistory();
                    $surgeHistory->surgeArea()->associate($surgeArea);
                    $surgeHistory->providers_count = $provider_request_map[$surgeArea->index][0];
                    $surgeHistory->requests_count = $provider_request_map[$surgeArea->index][1];
                    $factor =  $surgeHistory->requests_count /  $surgeHistory->providers_count; // supply/demand

                    // SET SURGE MULTIPLIER DELIMITERS
                    if($factor < $settings->min_surge)
                    {
                        // No surge multiplier
                        $factor = 1.0;
                    }
                    else
                    {
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
                    
                    //   # SAVE CURRENT SURGE MULTIPLIER FOR AREA
                    $surgeHistory->save();
                }
            }
        }
    }
}
