<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Passenger extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'address',
        'rating',
        'saved_payment_methods',
        'email_verified_at',
        'password',
        'customer_id',
    ];

    protected $hidden = [
        'password',
        'saved_payment_methods',
    ];

    public function getSavedPaymentMethodsAttribute($value)
    {
        return json_decode($value, true) ?? [];
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
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
}
