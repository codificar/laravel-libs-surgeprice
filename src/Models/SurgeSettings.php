<?php

namespace Codificar\SurgePrice\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeSettings extends Model
{
    const DAMPING = "DAMPING";
    const PRUNE = "PRUNE";
    const NONE = "NONE";

    protected $casts = [
        'heatmap_colors' => 'array',
        'heatmap_colors_pos' => 'array',
    ];

    protected $fillable =
    [
        'update_surge_window',
        'min_surge',
        'max_surge',
        'delimiter',
        'lof_neighbors',
        'lof_contamination',
        'model_files_path',
        'heatmap_expand_factor',
        'heatmap_colors',
        'heatmap_colors_pos',
    ];
}
