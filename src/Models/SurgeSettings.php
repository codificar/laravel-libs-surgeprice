<?php

namespace Codificar\SurgePrice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeSettings extends Model
{
    const DAMPING = "DAMPING";
    const PRUNE = "PRUNE";
    const NONE = "NONE";

    protected $fillable =
    [
        'update_surge_window',
        'min_surge',
        'max_surge',
        'delimiter',
        'lof_neighbors',
        'lof_contamination',
        'model_files_path'
    ];
}
