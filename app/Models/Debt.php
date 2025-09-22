<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = ['user_id', 'name', 'initial_amount', 'target_payoff_date', 'yearly_interest_rate', 'description'];

    public function user() { 
        return $this->belongsTo(User::class); 
    }

    public function projections() { 
        return $this->hasMany(DebtProjection::class); 
    }

    public function transactions() { 
        return $this->hasMany(DebtTransaction::class); 
    }
}
