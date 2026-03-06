<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceListings extends Command
{
    protected $signature = 'marketplace:sync-listings
                            {--account= : ID de conta específica (padrão: todas ativas)}';

    protected $description = 'Importa anúncios do Mercado Livre para marketplace_listings (sem criar produtos automaticamente)';

    public function handle(): int
    {
        $accounts = $this->resolveAccounts();

        if ($accounts->isEmpty()) {
            $this->warn('Nenhuma conta ativa do Mercado Livre encontrada.');
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->syncAccount($account);
        }

        return self::SUCCESS;
    }

    private function resolveAccounts()
    {
        $query = MarketplaceAccount::active()
            ->where('marketplace_type', MarketplaceType::MercadoLivre);

        if ($id = $this->option('account')) {
            $query->where('id', $id);
        }

        return $query->get();
    }

    private function syncAccount(MarketplaceAccount $account): void
    {
        $this->info("Sincronizando anúncios: [{$account->id}] {$account->account_name}");

        $service = new MercadoLivreService($account);
        $upserted = 0;
        $errors   = 0;

        try {
            foreach ($service->getListings() as $batch) {
                foreach ($batch as $mlItem) {
                    try {
                        $this->upsertListing($account, $mlItem);
                        $upserted++;
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error("SyncListings: erro no anúncio ML#{$mlItem['id']}: " . $e->getMessage());
                        $this->error("  Erro anúncio #{$mlItem['id']}: " . $e->getMessage());
                    }
                }
            }

            activity('marketplace')
                ->performedOn($account)
                ->withProperties(['upserted' => $upserted, 'errors' => $errors])
                ->log('Anúncios sincronizados');

            $this->info("  ✓ {$upserted} anúncios sincronizados" . ($errors ? ", {$errors} erros" : ''));

        } catch (\Throwable $e) {
            $account->update(['last_error' => $e->getMessage()]);
            Log::error("SyncListings: falha na conta {$account->id}: " . $e->getMessage());
            $this->error("  Falha na conta {$account->account_name}: " . $e->getMessage());
        }
    }

    private function upsertListing(MarketplaceAccount $account, array $ml): void
    {
        $existing = MarketplaceListing::where('marketplace_account_id', $account->id)
            ->where('external_id', $ml['id'])
            ->first();

        $data = [
            'title'              => $ml['title'] ?? "Anúncio #{$ml['id']}",
            'price'              => (float) ($ml['price'] ?? 0),
            'available_quantity' => (int) ($ml['available_quantity'] ?? 0),
            'status'             => $ml['status'] ?? 'active',
            'meta'               => [
                'ml_item_id'         => $ml['id'],
                'ml_status'          => $ml['status'] ?? null,
                'ml_permalink'       => $ml['permalink'] ?? null,
                'seller_sku'         => $ml['seller_custom_field'] ?? null,
                'category_id'        => $ml['category_id'] ?? null,
                'listing_type_id'    => $ml['listing_type_id'] ?? null,
                'has_variations'     => ! empty($ml['variations']),
                'family_name'        => $ml['family_name'] ?? null,
                'catalog_product_id' => $ml['catalog_product_id'] ?? null,
            ],
        ];

        if ($existing) {
            // Never overwrite product_id / product_quantity — those are set manually
            $existing->update($data);
        } else {
            MarketplaceListing::create(array_merge($data, [
                'marketplace_account_id' => $account->id,
                'external_id'            => $ml['id'],
                // product_id and product_quantity start as null/1 — linked manually
            ]));
        }
    }
}
