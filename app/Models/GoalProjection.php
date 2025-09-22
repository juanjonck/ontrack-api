<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalProjection extends Model
{
    protected $fillable = ['goal_id', 'description', 'amount', 'projection_date', 'status'];

    /**
     * Get the goal that owns the projection.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}