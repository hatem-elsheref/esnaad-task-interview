<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient extends Model
{
    protected $fillable = ['merchant_id', 'name', 'stock_quantity', 'consumed_quantity', 'remaining_quantity', 'is_notified'];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

}
