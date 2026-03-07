<?php

namespace App\Jobs;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceListing;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncListingQualityScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public readonly int $listingId,
    ) {}

    public function handle(): void
    {
        $listing = MarketplaceListing::with('marketplaceAccount')->find($this->listingId);

        if (! $listing) {
            return;
        }

        $account = $listing->marketplaceAccount;

        if (! $account || ! $account->credentials) {
            return;
        }

        if ($account->marketplace_type !== MarketplaceType::MercadoLivre) {
            return;
        }

        try {
            $service = new MercadoLivreService($account);
            $quality = $service->getItemQuality($listing->external_id);

            if (! empty($quality) && isset($quality['score'])) {
                $meta = $listing->meta ?? [];
                $meta['quality_score']     = (int) $quality['score'];
                $meta['quality_level']     = $quality['level_wording'] ?? ($quality['level'] ?? null);
                $meta['quality_synced_at'] = now()->toDateTimeString();
                $listing->update(['meta' => $meta]);

                Log::info("SyncListingQualityScore: listing#{$listing->id} ({$listing->external_id}) score={$quality['score']}");
            } else {
                Log::info("SyncListingQualityScore: listing#{$listing->id} ({$listing->external_id}) sem score disponível.");
            }
        } catch (\Throwable $e) {
            Log::warning("SyncListingQualityScore: listing#{$listing->id} erro: " . $e->getMessage());
            throw $e;
        }
    }
}
