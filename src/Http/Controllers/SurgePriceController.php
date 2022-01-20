<?php

namespace Codificar\SurgePrice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Codificar\SurgePrice\Models\SurgeSettings;
use Codificar\SurgePrice\Models\SurgeRegion;
use Codificar\SurgePrice\Models\SurgeCity;

class SurgePriceController extends Controller
{
    const SAVE_SETTINGS = 0;
    const CREATE_REGION = 1;
    const MANAGE_REGION = 2;

    public function index($response=-1)
    {
        $settings = SurgeSettings::first();
        $regions = SurgeRegion::all();
        foreach ($regions as $region)
        {
            $region->all_cities = $region->cities()->get()->keyBy('id')->toArray();
        }

        $delimiters = [
            SurgeSettings::DAMPING => asset('vendor/codificar/surgeprice/DAMPING.png'),
            SurgeSettings::PRUNE => asset('vendor/codificar/surgeprice/PRUNE.png'),
            SurgeSettings::NONE => asset('vendor/codificar/surgeprice/NONE.png')
        ];

        $response_message = "";
        switch ($response) {
            case SurgePriceController::SAVE_SETTINGS:
                $response_message = "Configurações salvas!";
                break;
            case SurgePriceController::CREATE_REGION:
                $regions []= new SurgeRegion();
                break;
            case SurgePriceController::MANAGE_REGION:
                $response_message = "Regiões atualizadas!";
                break;
            default:
                break;
        }

        return view('surgeprice::settings', [
            'settings' => $settings, 
            'regions' => $regions,
            'area_sizes' => SurgeRegion::$area_sizes,
            'states' => SurgeRegion::$states,
            'delimiters' => $delimiters,
            'response_message' => $response_message
        ]);
    }

    public function saveSettings(Request $request)
    {
        $settings = SurgeSettings::first()->fill($request->toArray());
        $settings->save();

        return $this->index(SurgePriceController::SAVE_SETTINGS);
    }

    public function createRegion(Request $request)
    {
        return $this->index(SurgePriceController::CREATE_REGION);
    }

    public function manageRegion(Request $request)
    {
        switch ($request->mode) {
            case 'save':
                //TODO: cidades
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
            case 'delete':
                SurgeRegion::find($request->id)->delete();
                break;
            default:
                # code...
                break;
        }
        return $this->index(SurgePriceController::MANAGE_REGION);
    }
}
