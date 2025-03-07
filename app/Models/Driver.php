<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Storage;
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
        'id_card_image',
        'license_image',
        'driving_license_image',
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
    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    public function deleteLicense()
    {
        if (Storage::disk('public')->directoryExists('driver_licenses/' . $this->email)) {
            if (!Storage::disk('public')->deleteDirectory('driver_licenses/' . $this->email)) {
                throw new Exception('Error deleting account');
            }
        }
    }
}
