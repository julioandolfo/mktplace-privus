<?php

namespace App\Jobs;

use App\Models\MarketplaceListing;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStockToML implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $listingId,
        public readonly int $newQuantity,
    ) {}

    public function handle(): void
    {
        $listing = MarketplaceListing::with('marketplaceAccount')->find($this->listingId);

        if (! $listing) {
            return;
        }

        $account = $listing->marketplaceAccount;

        if (! $account || ! $account->credentials || ! $account->is_healthy) {
            return;
        }

        // Respect product_quantity multiplier (e.g. kit of 100 units)
        $quantity = (int) floor($this->newQuantity / max(1, $listing->product_quantity));

        try {
            $service = new MercadoLivreService($account);
            $service->updateItem($listing->external_id, ['available_quantity' => $quantity]);

            $listing->update(['available_quantity' => $quantity]);

            Log::info("SyncStockToML: listing {$listing->external_id} atualizado → qty={$quantity}");

        } catch (\Throwable $e) {
            Log::error("SyncStockToML: erro listing {$listing->external_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
