<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MiscCategory extends Model
{
    use HasFactory;

    protected $table = 'misc_categories';
    protected $fillable = [
        'name',
        'description',
        'product_count',
    ];
}
