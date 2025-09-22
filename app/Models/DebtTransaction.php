<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtTransaction extends Model
{
    protected $fillable = ['debt_id', 'planned_projection_id', 'amount', 'description', 'transaction_date'];

    public function debt() { 
        return $this->belongsTo(Debt::class); 
    }

    public function plannedProjection() { 
        return $this->belongsTo(DebtProjection::class, 'planned_projection_id'); 
    }
}
