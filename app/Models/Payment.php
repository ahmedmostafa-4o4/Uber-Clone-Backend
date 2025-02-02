<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'ride_id',
        'payment_method',
        'payment_method_id',
        'status',
        'amount',
        'currency',
        'payment_intent_id',
        'transaction_id',
        'metadata',
        'failure_reason',
        'captured',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

}
