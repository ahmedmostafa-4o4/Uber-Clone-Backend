<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    protected $fillable = [
        'passenger_id',
        'driver_id',
        'pickup_location',
        'dropoff_location',
        'region',
        'start_time',
        'end_time',
        'distance',
        'fare',
        'status'
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    public function passenger() {
        return $this->belongsTo(Passenger::class);
    }

    public function driver() {
        return $this->belongsTo(Driver::class);
    }
    public function feedback() {
        return $this->hasOne(Feedback::class);
    }
}
