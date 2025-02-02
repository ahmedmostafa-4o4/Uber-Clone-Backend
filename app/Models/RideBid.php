<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideBid extends Model
{
    /** @use HasFactory<\Database\Factories\RideBidFactory> */
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'driver_id',
        'price',
        'status'
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
