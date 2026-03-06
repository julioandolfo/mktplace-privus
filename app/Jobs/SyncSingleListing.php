<?php

namespace App\Jobs;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSingleListing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $accountId,
        public readonly string $itemId,
    ) {}

    public function handle(): void
    {
        $account = MarketplaceAccount::find($this->accountId);

        if (! $account || ! $account->credentials) {
            Log::warning("SyncSingleListing: conta {$this->accountId} não encontrada ou sem credenciais.");
            return;
        }

        if ($account->marketplace_type !== MarketplaceType::MercadoLivre) {
            return;
        }

        try {
            $service = new MercadoLivreService($account);
            $item    = $service->getItem($this->itemId);

            if (empty($item['id'])) {
                Log::warning("SyncSingleListing: item ML#{$this->itemId} retornou vazio.");
                return;
            }

            MarketplaceListing::updateOrCreate(
                [
                    'marketplace_account_id' => $account->id,
                    'external_id'            => $item['id'],
                ],
                [
                    'title'              => $item['title'] ?? 'Sem título',
                    'price'              => $item['price'] ?? 0,
                    'available_quantity' => $item['available_quantity'] ?? 0,
                    'status'             => $item['status'] ?? 'unknown',
                    'meta'               => [
                        'ml_item_id'          => $item['id'],
                        'ml_status'           => $item['status'] ?? null,
                        'ml_permalink'        => $item['permalink'] ?? null,
                        'seller_sku'          => $item['seller_custom_field'] ?? null,
                        'category_id'         => $item['category_id'] ?? null,
                        'listing_type_id'     => $item['listing_type_id'] ?? null,
                        'has_variations'      => ! empty($item['variations']),
                        'variations_count'    => count($item['variations'] ?? []),
                        'family_name'         => $item['family_name'] ?? null,
                        'catalog_product_id'  => $item['catalog_product_id'] ?? null,
                        'thumbnail'           => $item['pictures'][0]['url'] ?? ($item['thumbnail'] ?? null),
                        'sold_quantity'       => $item['sold_quantity'] ?? 0,
                        'is_free_shipping'    => $item['shipping']['free_shipping'] ?? false,
                        'is_fulfillment'      => in_array('fulfillment', $item['tags'] ?? []),
                        'condition'           => $item['condition'] ?? null,
                    ],
                ]
            );

            Log::info("SyncSingleListing: item ML#{$this->itemId} sincronizado com sucesso.");

        } catch (\Throwable $e) {
            Log::error("SyncSingleListing: erro item ML#{$this->itemId}: " . $e->getMessage());
            throw $e;
        }
    }
}
