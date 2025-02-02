<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $fillable = [
        "ride_id",
        "passenger_rating",
        "driver_rating",
        "comments",
        "issues_reported",
        "resolution_status",
    ];

    public function ride() {
        return $this->belongsTo(Ride::class);
    }
}
