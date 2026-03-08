<?php

namespace App\Providers;

use App\Models\OrderItem;
use App\Models\StockItem;
use App\Observers\OrderItemObserver;
use App\Observers\StockItemObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        StockItem::observe(StockItemObserver::class);
        OrderItem::observe(OrderItemObserver::class);
    }
}
