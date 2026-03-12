<?php

namespace App\Console\Commands;

use App\Jobs\SyncListingQualityScore;
use App\Models\MarketplaceListing;
use Illuminate\Console\Command;

class SyncListingQuality extends Command
{
    protected $signature = 'marketplace:sync-quality
                            {--account= : ID de conta específica (padrão: todas)}';

    protected $description = 'Dispara jobs para sincronizar qualidade dos anúncios do Mercado Livre';

    public function handle(): int
    {
        $query = MarketplaceListing::query()
            ->whereHas('marketplaceAccount', fn ($q) => $q->whereNotNull('credentials'))
            ->select('id', 'marketplace_account_id', 'external_id');

        if ($accountId = $this->option('account')) {
            $query->where('marketplace_account_id', $accountId);
        }

        $listings = $query->get();

        if ($listings->isEmpty()) {
            $this->info('Nenhum anúncio encontrado para sincronizar qualidade.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($listings as $listing) {
            SyncListingQualityScore::dispatch($listing->id)->onQueue('default');
            $dispatched++;
        }

        $this->info("Qualidade: {$dispatched} job(s) disparados.");

        return self::SUCCESS;
    }
}
