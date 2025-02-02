<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
class Driver extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'address',
        'license_number',
        'rating',
        'status',
        'driving_experience',
        'car_model',
        'license_plate',
        'car_color',
        'manufacturing_year',
        'insurance_info',
        'registration_info',
        'is_verified'
    ];

    protected $hidden = [
        'password'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
    public function verify()
    {
        $this->is_verified = 1;
        $this->status = 'inactive';
    }
    public function decline()
    {
        $this->is_verified = 0;
        $this->status = 'inactive';
    }
}
