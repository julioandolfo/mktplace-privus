<?php

namespace App\Observers;

use App\Jobs\SyncStockToML;
use App\Models\MarketplaceListing;
use App\Models\StockItem;
use Illuminate\Support\Facades\Log;

class StockItemObserver
{
    /**
     * Push updated stock to all linked ML listings when quantity changes.
     */
    public function updated(StockItem $stockItem): void
    {
        if (! $stockItem->isDirty('quantity')) {
            return;
        }

        $productId = $stockItem->product_id;
        if (! $productId) {
            return;
        }

        // Compute total available stock across all locations for this product
        $totalStock = StockItem::where('product_id', $productId)->sum('quantity');

        // Find all ML listings linked to this product with auto_update_stock enabled
        $listings = MarketplaceListing::with('marketplaceAccount')
            ->where('product_id', $productId)
            ->get();

        foreach ($listings as $listing) {
            $account = $listing->marketplaceAccount;

            if (! $account || ! $account->is_healthy) {
                continue;
            }

            $autoUpdate = $account->settings['auto_update_stock'] ?? false;
            if (! $autoUpdate) {
                continue;
            }

            Log::info("StockItemObserver: produto #{$productId} → listing {$listing->external_id} → qty={$totalStock}");

            SyncStockToML::dispatch($listing->id, $totalStock)
                ->onQueue('default')
                ->delay(now()->addSeconds(5)); // small delay to batch multiple changes
        }
    }
}
