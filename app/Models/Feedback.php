<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = "feedbacks";
    protected $fillable = [
        "ride_id",
        "passenger_id",
        "driver_id",
        "passenger_rating",
        "driver_rating",
        "driver_comments",
        "passenger_comments",
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }
}
