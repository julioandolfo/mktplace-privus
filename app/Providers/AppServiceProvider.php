<?php

namespace App\Providers;

use App\Models\DesignAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Observers\DesignAssignmentObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
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
        Order::observe(OrderObserver::class);
        DesignAssignment::observe(DesignAssignmentObserver::class);
    }
}
