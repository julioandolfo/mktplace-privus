<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceType;
use App\Enums\ProductStatus;
use App\Models\MarketplaceAccount;
use App\Models\Product;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceListings extends Command
{
    protected $signature = 'marketplace:sync-listings
                            {--account= : ID de conta específica (padrão: todas ativas)}';

    protected $description = 'Importa anúncios/listings do Mercado Livre para o catálogo de produtos';

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
        $updated = 0;
        $created = 0;
        $errors  = 0;

        try {
            foreach ($service->getListings() as $batch) {
                foreach ($batch as $mlItem) {
                    try {
                        $result = $this->upsertListing($mlItem);
                        if ($result === 'created') {
                            $created++;
                        } else {
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error("SyncListings: erro no anúncio ML#{$mlItem['id']}: " . $e->getMessage());
                        $this->error("  Erro anúncio #{$mlItem['id']}: " . $e->getMessage());
                    }
                }
            }

            activity('marketplace')
                ->performedOn($account)
                ->withProperties(['created' => $created, 'updated' => $updated, 'errors' => $errors])
                ->log('Anúncios sincronizados');

            $this->info("  ✓ {$created} criados, {$updated} atualizados" . ($errors ? ", {$errors} erros" : ''));

        } catch (\Throwable $e) {
            $account->update(['last_error' => $e->getMessage()]);
            Log::error("SyncListings: falha na conta {$account->id}: " . $e->getMessage());
            $this->error("  Falha na conta {$account->account_name}: " . $e->getMessage());
        }
    }

    /**
     * @return string 'created' | 'updated'
     */
    private function upsertListing(array $ml): string
    {
        $mlItemId = $ml['id'];
        $sku      = $ml['seller_custom_field'] ?? null;
        $price    = (float) ($ml['price'] ?? 0);
        $mlStatus = $ml['status'] ?? 'active'; // active, paused, closed, deleted

        $meta = [
            'ml_item_id'  => $mlItemId,
            'ml_status'   => $mlStatus,
            'ml_permalink' => $ml['permalink'] ?? null,
        ];

        // Try to find existing product by SKU or by ml_item_id in meta
        $product = null;

        if ($sku) {
            $product = Product::where('sku', $sku)->first();
        }

        if (! $product) {
            // Search by ml_item_id stored in meta
            $product = Product::whereJsonContains('meta->ml_item_id', $mlItemId)->first();
        }

        if ($product) {
            $product->update([
                'price' => $price,
                'meta'  => array_merge($product->meta ?? [], $meta),
            ]);
            return 'updated';
        }

        // Create new product from ML listing
        Product::create([
            'name'   => $ml['title'] ?? "Anúncio ML #{$mlItemId}",
            'sku'    => $sku ?? $mlItemId,
            'price'  => $price,
            'status' => $mlStatus === 'active' ? ProductStatus::Active : ProductStatus::Inactive,
            'meta'   => $meta,
        ]);

        return 'created';
    }
}
