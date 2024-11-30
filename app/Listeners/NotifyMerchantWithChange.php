<?php

namespace App\Listeners;

use App\Events\ChangingInIngredientAmount;
use App\Notifications\StockAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyMerchantWithChange
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ChangingInIngredientAmount $event): void
    {
        $event->ingredient->update([
            'is_notified' => true
        ]);

        $event->ingredient->merchant->notify(new StockAlertNotification($event->ingredient));
    }
}
