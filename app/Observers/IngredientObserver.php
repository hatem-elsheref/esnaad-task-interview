<?php

namespace App\Observers;

use App\Models\Ingredient;

class IngredientObserver
{
    public function created(Ingredient $ingredient): void
    {
        $this->updateRemainingQuantity($ingredient);
    }

    private function updateRemainingQuantity(Ingredient $ingredient): void
    {
        Ingredient::withoutEvents(function () use ($ingredient) {
            $ingredient->update([
                'remaining_quantity' => $ingredient->stock_quantity - $ingredient->consumed_quantity
            ]);
        });
    }
}
