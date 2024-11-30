<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = ['name', 'stock_quantity', 'consumed_quantity', 'remaining_quantity', 'is_notified'];
}
