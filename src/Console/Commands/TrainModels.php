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
 * Generate the Machine Learning Models (LOF & K-means) for surge area prediction.
 * 
 * This command changes the models training list, and consequently the cluster (surge) areas positions.
 * As it may change the geographic configuration of the surge areas,
 * it must be executed once per month at least, on new regions,
 * or over a longer period in established regions,
 * in order to keep a reliable surge historic.
 * 
 **/
class TrainModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ml:train_models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the Machine Learning Models (LOF & K-means) for surge area prediction';

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
        $trainedData = [];
        $prefixesLentgh = [];
        $regionCities = [];
        $currentRegions = [];
        
        // Get all regions.
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $currentRegions[$region->state] = $region;
            $trainedData[$region->state] = [];
            // Set prefix length to calculate total cluster areas.
            switch ($region->area_size) {
                case 'L':
                    # Large: 3 digits prefix.
                    $prefixesLentgh[$region->state] = 3;
                    break;
                case 'M':
                    # Medium: 4 digits prefix.
                    $prefixesLentgh[$region->state] = 4;
                    break;
                default:
                    # Small: 5 digits prefix.
                    $prefixesLentgh[$region->state] = 5;
                    break;
            }
            // Get region cities into set.
            $regionCities[$region->state] = $region->cities()->pluck('enabled','name')->toArray();
        }

        
        // Create the training lists (one per region).
        // Add new cities to existing regions.
        $end = Carbon::now()->subDays(1);
        $begin = Carbon::now()->subYears(1);
        $requests = Requests::where('created_at', '>=', $begin)
        ->where('created_at', '<=', $end)
        ->orderBy('created_at')
        ->get();
        // Iterate over requests between yesterday and 1 year ago.
        foreach($requests as $i => $request)
        {
            // Split request address to get needed info.
            $split_addr = explode(",", $request->src_address);
            // Add country to search address.
            $country = trim(array_pop($split_addr));
            // Is it a valid postal code?
            $postal_code = trim(array_pop($split_addr));
            if(preg_match('/[0-9]{5}-[0-9]{3}/', $postal_code))
            {                
                // Get state.
                $cityState = explode("-", array_pop($split_addr));
                $state = trim(array_pop($cityState));
                // Is it a request from a valid region?
                if(array_key_exists($state, $currentRegions))
                {
                    // Get postal code prefix for request.
                    $prefix = substr($postal_code, 0, $prefixesLentgh[$state]);
                    // Get request city.
                    if(count($cityState) > 0)
                        $city = trim(array_pop($cityState));
                    else
                    {
                        $suffixCity = explode("-", array_pop($split_addr));
                        $city = trim(array_pop($suffixCity));
                    }
                    // The city is not in the region (new city)?
                    if(!array_key_exists($city, $regionCities[$state]) &&
                    // Is it a valid city name?
                    !preg_match('~[0-9]+~', $city) && 
                    preg_match('~^\p{Lu}~u', $city)
                    )
                    {
                        // Insert city in the region and enable it.
                        $newCity = new SurgeCity();
                        $newCity->name = $city;
                        $newCity->region()->associate($currentRegions[$state]);
                        $newCity->save();
                        $regionCities[$state][$city] = true;
                    }
                    // Is the city already in the region and enabled?
                    if(array_key_exists($city, $regionCities[$state]) && $regionCities[$state][$city])
                    {
                        // Add request position to training list for region ML model.
                        // (LAT, LNG, PREFIX)
                        $trainedData[$state] []= implode(',', [$request->latitude, $request->longitude, $prefix]);
                    }
                }
            }
        }

        $settings = SurgeSettings::first();
        $train_file = 'request-train.csv';
        // Iterate over regions in set.
        foreach ($trainedData as $state => $regionTrainedData)
        {
            // Create model files directory.
            $ml_path = $settings->model_files_path.'/'.$state.'/';
            if (!file_exists($ml_path))
            {
                mkdir($ml_path, 0777, true);
            }
            //  Save the train file, using path provided in settings.
            $train = implode(PHP_EOL,$regionTrainedData);
            file_put_contents($ml_path.$train_file, $train);
            //  Run the model train using Python ML to obtain TOTAL AREAS, CENTROIDS and INDEXES.
            $process = new Process(['python', __DIR__.'/../../resources/scripts/train-models.py',
                                    '-t',$train_file,
                                    '-m', $currentRegions[$state]->min_area_requests, 
                                    '-n', $settings->lof_neighbors, 
                                    '-c', $settings->lof_contamination,
                                    '-p' ,$ml_path]);
            $process->run();
            // New model generated.
            if (file_exists($ml_path.'/centroids.csv'))
            {
                $open = fopen($ml_path.'/centroids.csv', "r");
                while (($data = fgetcsv($open, 1000, ",")) !== FALSE) 
                {        
                    $centroids[] = array_map('floatval', $data); 
                }
            
                fclose($open);
                // Update total areas for the region in DB.
                $currentRegions[$state]->total_areas = count($centroids);
                $currentRegions[$state]->save();
                // Remove legacy surge areas (clusters) and related surge history.
                foreach ($region->surgeAreas()->get() as $surgeArea)
                {
                    $surgeArea->surgeHistory()->delete();
                }
                $currentRegions[$state]->surgeAreas()->delete();
                //  Save new surge areas (clusters) centroids and indexes for region in DB.
                foreach ($centroids as $i => $centroid)
                {
                    $surgeArea = new SurgeArea();
                    $surgeArea->region()->associate($currentRegions[$state]);
                    $surgeArea->index = $i;
                    $surgeArea->centroid = new Point($centroid[0], $centroid[1]);
                    $surgeArea->save();
                }
            }
        }

        // Update the surge multiplier for the new generated surge areas.
        $this->call('ml:predict_data');
    }
}
