<?php

namespace Codificar\SurgePrice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeRegion extends Model
{

    // Possible states
    public static $states = ['AC','AL','AP','AM','BA','CE','DF',
    'ES','GO','MA','MT','MS','MG','PA',
    'PB','PR','PE','PI','RJ','RN','RS',
    'RO','RR','SC','SP','SE','TO'];

    // Area size options
    public static $area_sizes = ['S', 'M', 'L'];

    /**
     * Get all the cities associated with this region, if any.
     * 
     * @return SurgeCity[]
     */
    public function cities()
    {
        return $this->hasMany('Codificar\SurgePrice\Models\SurgeCity', 'region_id');
    }

    /**
     * Get all the surge areas associated with this region, if any.
     * 
     * @return SurgeArea[]
     */
    public function surgeAreas()
    {
        return $this->hasMany('Codificar\SurgePrice\Models\SurgeArea', 'region_id');
    }

    protected $attributes = [
        'country' => 'Brasil', // Edit for international support
        'min_area_requests' => 0
    ];

    protected $fillable =
    [
        'country',
        'state',
        'area_size',
        'min_area_requests',
    ];
}
