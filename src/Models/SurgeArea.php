<?php

namespace Codificar\SurgePrice\Models;

use Illuminate\Database\Eloquent\Model;
use Grimzy\LaravelMysqlSpatial\Eloquent\SpatialTrait;
use Grimzy\LaravelMysqlSpatial\Types\Point;

class SurgeArea extends Model
{
    use SpatialTrait;
    
    public $timestamps = false;
    
    protected $spatialFields = [
        'centroid'
    ];

    /**
     * Get the region associated with the surge area
     * 
     * @return SurgeRegion
     */
    public function region()
    {
        return $this->belongsTo('Codificar\SurgePrice\Models\SurgeRegion');
    }

    /**
     * Get the surge history with this surge area, if any.
     * 
     * @return SurgeHistory[]
     */
    public function surgeHistory()
    {
        return $this->hasMany('Codificar\SurgePrice\Models\SurgeHistory', 'surge_area_id');
    }
}
