<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otp extends Model {
    use HasFactory;

    protected $fillable = ['email', 'otp', 'expires_at'];

    public function isExpired() {
        return Carbon::now()->greaterThan($this->expires_at);
    }
}

