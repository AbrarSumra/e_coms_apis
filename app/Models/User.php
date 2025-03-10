<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $table = 'users';
    protected $fillable = [
        'first_name', 'last_name', 'email', 'email_verified_at', 
        'mobile', 'role', 'fcm_token', 'wishlist', 'otp', 'otp_expires_at', 'password', 'profile_image',
        'date_of_birth', 'address', 'city', 'street', 'house_no',
        'zipcode', 'country', 'token'
    ];

    protected $hidden = [
        'password', 'otp', 'otp_expires_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'wishlist' => 'array',
    ];
}


