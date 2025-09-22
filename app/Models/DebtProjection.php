<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtProjection extends Model
{
    protected $fillable = ['debt_id', 'description', 'amount', 'projection_date', 'status'];

    public function debt() { 
        return $this->belongsTo(Debt::class); 
    }

    public function transactions() { 
        return $this->hasMany(DebtTransaction::class, 'planned_projection_id'); 
    }
}
