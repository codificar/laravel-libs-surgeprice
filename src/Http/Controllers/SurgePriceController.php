<?php

namespace Codificar\SurgePrice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Symfony\Component\Process\Process;
use Grimzy\LaravelMysqlSpatial\Types\Point;

use Codificar\SurgePrice\Models\SurgeSettings;
use Codificar\SurgePrice\Models\SurgeRegion;
use Codificar\SurgePrice\Models\SurgeCity;
use Codificar\SurgePrice\Models\SurgeArea;
use Codificar\SurgePrice\Models\SurgeHistory;

class SurgePriceController extends Controller
{
    const SAVE_SETTINGS = 1;
    const CREATE_REGION = 2;
    const MANAGE_REGION = 3;

    // Surge price settings page.
    public function index(Request $response)
    {
        // Get related data.
        $settings = SurgeSettings::first();
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $region->all_cities = $region->cities()->get()->keyBy('id')->toArray();
        }

        // Get delimiter pictures addresses.
        $delimiters = [
            SurgeSettings::DAMPING => asset('vendor/codificar/surgeprice/DAMPING.png'),
            SurgeSettings::PRUNE => asset('vendor/codificar/surgeprice/PRUNE.png'),
            SurgeSettings::NONE => asset('vendor/codificar/surgeprice/NONE.png')
        ];

        // Get regions cluster sizes pictures adresses.
        $sizes_figs = [
            'S' => asset('vendor/codificar/surgeprice/S.jpg'),
            'M' => asset('vendor/codificar/surgeprice/M.jpg'),
            'L' => asset('vendor/codificar/surgeprice/L.jpg')
        ];

        // Feedback treatment.
        $response_message = "";
        switch ($response->status) {
            case SurgePriceController::SAVE_SETTINGS:
                $response_message = "Configurações salvas!\\r\\rAtualize os modelos de Machine Learning";
                break;
            case SurgePriceController::CREATE_REGION:
                $regions []= new SurgeRegion();
                break;
            case SurgePriceController::MANAGE_REGION:
                $response_message = "Regiões atualizadas!\\r\\rAtualize os modelos de Machine Learning";
                break;
            default:
                break;
        }

        return view('surgeprice::settings', [
            'settings' => $settings, 
            'regions' => $regions,
            'area_sizes' => SurgeRegion::$area_sizes,
            'sizes_figs' => $sizes_figs,
            'states' => SurgeRegion::$states,
            'delimiters' => $delimiters,
            'response_message' => $response_message
        ]);
    }

    // Save the surge settings and redirect to base page.
    public function saveSettings(Request $request)
    {
        $settings = SurgeSettings::first()->fill($request->toArray());
        $settings->save();

        return redirect()->action([SurgePriceController::class, 'index'],['status' => SurgePriceController::SAVE_SETTINGS]);
    }

    // // Add new region empty row.
    public function createRegion(Request $request)
    {
        return redirect()->action([SurgePriceController::class, 'index'],['status' => SurgePriceController::CREATE_REGION]);
    }

    // Manage region.
    public function manageRegion(Request $request)
    {
        switch ($request->mode) {
            // Save region settings.
            case 'save':
                if($updatedCities = json_decode($request->toArray()['cities']))
                {
                    foreach ($updatedCities as $updatedCity)
                    {
                        $city = SurgeCity::find($updatedCity->id);
                        $city->enabled = $updatedCity->enabled;
                        $city->save();
                    }
                }
                unset($request->mode);
                $region = SurgeRegion::find($request->id);
                if(!$region)
                    $region = new SurgeRegion();
                $region->fill($request->toArray());
                $region->save();
                break;
            // Delete region and related entities (areas, history).
            case 'delete':
                $region = SurgeRegion::find($request->id);
                foreach ($region->surgeAreas()->get() as $surgeArea)
                {
                    $surgeArea->surgeHistory()->delete();
                }
                $region->surgeAreas()->delete();
                $region->cities()->delete();
                $region->delete();
                $path = SurgeSettings::first()->model_files_path;
                // Delete region related ML files.
                $this->deleteDirectory($path.DIRECTORY_SEPARATOR.$region->state);
                break;
            default:
                break;
        }
        return redirect()->action([SurgePriceController::class, 'index'],['status' => SurgePriceController::MANAGE_REGION]);
    }

    // Delete a directory and related files.
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
    
        if (!is_dir($dir)) {
            return unlink($dir);
        }
    
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
    
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
    
        }
    
        return rmdir($dir);
    }
    
    // Get the surge multiplier given a geolocation.
    public function getSurgeMultiplier($latitude, $longitude)
    {
        $settings = SurgeSettings::first();
        
        // Use Machine Learning to predict geolocation region and surge area, if any.
        $process = new Process(['python3', __DIR__.'/../../resources/scripts/predict-single.py',
                                '-a', $latitude,
                                '-o', $longitude,
                                '-p', $settings->model_files_path]);
        $process->run();

        [$state, $areaIndex] = explode(' ', trim($process->getOutput()));

        // Geolocation is in a valid region, return surge area related data.
        if($state != '-')
        {
            $region = SurgeRegion::where('state',$state)->first();
            $surgeArea = $region->surgeAreas()->where('index', $areaIndex)->first();
            $history = $surgeArea->surgeHistory()->orderBy('created_at', 'DESC')->first();
            return [
                'status' => 200,
                'state' => $state,
                'area' => [
                    'index' => $surgeArea->index,
                    'centroid' => [$surgeArea->centroid->getLat(), $surgeArea->centroid->getLng()],
                ],
                'surge' => $history->multiplier
            ];
        }
        // Geolocation is an outlier, return default 1x multiplier.
        return [
            'status' => 404,
            'surge' => 1
        ];
    }

    // Get the heatmaps with surge data for all active regions.
    public function heatMap(Request $sessionRequest)
    {
        $settings = SurgeSettings::first();
        $heatmaps = [];
        $centroids = [];
        // Iterate over all regions.
        foreach (SurgeRegion::all() as $region)
        {
            $area_multiplier = [];
            $currentMax = 0;
            // Iterate over all areas in region.
            foreach($region->surgeAreas as $surgeArea)
            {
                $history = $surgeArea->surgeHistory()->orderBy('created_at', 'DESC')->first();
                $area_multiplier[$surgeArea->index] = $history->multiplier;
                // No surge history, skip area.
                if(!$history || $history->multiplier < $settings->min_surge)
                    continue;
                $currentMax = max($currentMax, $history->multiplier);

                // Generate labels for surge area multipliers.
                $centroids []=
                [
                    'key' => $region->state.$surgeArea->index,
                    'location' =>
                    [
                        'latitude' => $surgeArea->centroid->getLat(),
                        'longitude' => $surgeArea->centroid->getLng(),
                    ],
                    'multiplier' => sprintf("%.1f", $history->multiplier),
                ];
            }
            // No surge in region, skip it.
            if($currentMax < $settings->min_surge)
                continue;
            
            // Initalize region heatmap.
            $heatmaps[$region->state] = ['key' => $region->id];

            // Get region heatmap points using supersampling.
            $process = new Process(['python3', __DIR__.'/../../resources/scripts/heatmap.py',
            '-s', $region->state,
            '-e', $settings->heatmap_expand_factor,
            '-m', implode(",", $area_multiplier).',',
            '-p', $settings->model_files_path]);
            $process->run();

            // Get output and populate heatmap set.
            foreach(explode("\n",trim($process->getOutput())) as $entry)
            {
                $latlngIndex = array_map('floatval',explode(" ", $entry));
                
                $areaIndex = intval($latlngIndex[2]);
                $multiplier = $area_multiplier[$areaIndex];
                if($multiplier >= $settings->min_surge)
                    $heatmaps[$region->state]['points'] []= ['latitude' => $latlngIndex[0], 'longitude' => $latlngIndex[1], 'weight' => $multiplier];
            }

            // Normalize color positions in gradient by current max surge.
            $gradientPoints = $settings->heatmap_colors_pos;
            $factor = floatval($currentMax) / floatval($settings->max_surge);
            $factor = min(1, $factor);
            // Set region heatmap gradient.
            $heatmaps[$region->state]['gradient'] =
            [
                'colors' => $settings->heatmap_colors,
                'startPoints' => array_map(function($el) use($factor) { return $el / $factor; }, $gradientPoints),
                'radius' => 10,
            ];

            
        }

        return
        [
            'heatmaps' => array_values($heatmaps),
            'labels' => $centroids,
        ];
    }
}
