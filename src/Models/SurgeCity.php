<?php

namespace Codificar\Surgeprice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeCity extends Model
{
    public $timestamps = false;
    
    protected $casts = [
        'enabled' => 'boolean',
    ];
     /**
     * Get the region associated with the city
     * 
     * @return SurgeRegion
     */
    public function region()
    {
        return $this->belongsTo('SurgeRegion');
    }
}
