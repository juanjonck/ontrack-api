<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = ['user_id', 'name', 'target_amount', 'target_date', 'yearly_interest_rate', 'description'];

    public function user() { 
        return $this->belongsTo(User::class); 
    }

    public function projections() { 
        return $this->hasMany(GoalProjection::class); 
    }

    public function transactions() { 
        return $this->hasMany(GoalTransaction::class); 
    }
}
