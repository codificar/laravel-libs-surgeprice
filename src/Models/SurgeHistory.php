<?php

namespace Codificar\Surgeprice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeHistory extends Model
{
    protected $table = 'surge_history';

    /**
     * Get the surge area associated with the surge history
     * 
     * @return SurgeArea
     */
    public function surgeArea()
    {
        return $this->belongsTo('SurgeArea');
    }
}
