<?php

namespace Codificar\Surgeprice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeRegion extends Model
{
    /**
     * Get all the cities associated with this region, if any.
     * 
     * @return SurgeCity[]
     */
    public function cities()
    {
        return $this->hasMany('SurgeCity', 'region_id');
    }

    /**
     * Get all the surge areas associated with this region, if any.
     * 
     * @return SurgeArea[]
     */
    public function surgeAreas()
    {
        return $this->hasMany('SurgeArea', 'region_id');
    }
}
