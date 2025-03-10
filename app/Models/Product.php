<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products'; // Table name changed to 'products'

    protected $fillable = [
        'name',
        'description',
        'price',
        'image_url',
        'gallery_urls',
        'is_available',
        'category_id',
        'sub_category_id',
        'misc_id',
        'brand',
        'rating',
        'reviews_count',
        'item_qty',
        'inventory_quantity',
        'low_stock_threshold',
        'sku',
        'is_in_stock',
        'manage_stock',
        'wishlist_liked',
    ];

    protected $casts = [
        'gallery_urls' => 'array',
        'is_available' => 'integer',
        'is_in_stock' => 'integer',
        'manage_stock' => 'integer',
        'wishlist_liked' => 'boolean',
    ];
}
