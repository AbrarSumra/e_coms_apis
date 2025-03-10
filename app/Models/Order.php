<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'email', 'mobile', 'address',
        'payment_method', 'total_price', 'status', 'order_items'
    ];

    protected $casts = [
        'order_items' => 'array', // Cast JSON to array
    ];
}
