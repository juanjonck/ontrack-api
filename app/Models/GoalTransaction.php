<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalTransaction extends Model
{
    protected $fillable = ['goal_id', 'planned_projection_id', 'amount', 'description', 'transaction_date'];
}
