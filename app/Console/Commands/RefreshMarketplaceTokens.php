<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\MarketplaceAccount;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshMarketplaceTokens extends Command
{
    protected $signature   = 'marketplace:refresh-tokens {--force : Refresh all OAuth tokens regardless of expiry}';
    protected $description = 'Automatically refresh expiring OAuth tokens for marketplace accounts';

    public function handle(): int
    {
        $force = $this->option('force');

        $accounts = MarketplaceAccount::all()->filter(function ($account) use ($force) {
            if (! $account->marketplace_type->supportsOAuth()) {
                return false;
            }

            try {
                $creds = $account->credentials ?? [];
            } catch (\Exception) {
                return false;
            }

            if (empty($creds['refresh_token'])) {
                return false;
            }

            if ($force) {
                return true;
            }

            // Refresh if token expires within the next 30 minutes or is already expired
            if (! $account->token_expires_at) {
                return true;
            }

            return $account->token_expires_at->diffInMinutes(now(), absolute: true) <= 30
                || $account->token_expires_at->isPast();
        });

        if ($accounts->isEmpty()) {
            $this->info('No tokens need refreshing.');
            return self::SUCCESS;
        }

        $refreshed = 0;
        $failed    = 0;

        foreach ($accounts as $account) {
            $type = $account->marketplace_type->value;

            try {
                $creds        = $account->credentials ?? [];
                $clientId     = SystemSetting::get('marketplaces', "{$type}_client_id");
                $clientSecret = SystemSetting::get('marketplaces', "{$type}_client_secret");
                $config       = config("marketplaces.{$type}");

                if (empty($clientId) || empty($clientSecret) || empty($config['token_url'])) {
                    $this->warn("  [{$account->account_name}] Missing client credentials or token_url — skipping.");
                    $failed++;
                    continue;
                }

                $response = Http::timeout(15)->asForm()->post($config['token_url'], [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $creds['refresh_token'],
                ]);

                if (! $response->successful()) {
                    $error = $response->json('message') ?? "HTTP {$response->status()}";
                    $this->error("  [{$account->account_name}] Refresh failed: {$error}");

                    $account->update([
                        'status'     => AccountStatus::Error,
                        'last_error' => "Token refresh failed: {$error}",
                    ]);

                    Log::error("Marketplace token refresh failed for [{$account->id}] {$account->account_name}", [
                        'status'   => $response->status(),
                        'response' => $response->json(),
                    ]);

                    $failed++;
                    continue;
                }

                $tokens    = $response->json();
                $expiresAt = isset($tokens['expires_in'])
                    ? now()->addSeconds($tokens['expires_in'])
                    : null;

                $account->update([
                    'credentials' => array_merge($creds, [
                        'access_token'  => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'] ?? $creds['refresh_token'],
                        'token_type'    => $tokens['token_type'] ?? 'Bearer',
                    ]),
                    'token_expires_at' => $expiresAt,
                    'status'           => AccountStatus::Active,
                    'last_error'       => null,
                ]);

                $this->info("  [{$account->account_name}] Token refreshed successfully." .
                    ($expiresAt ? " Expires: {$expiresAt->format('d/m/Y H:i')}" : ''));

                $refreshed++;

            } catch (\Exception $e) {
                $this->error("  [{$account->account_name}] Exception: {$e->getMessage()}");
                Log::error("Marketplace token refresh exception for [{$account->id}] {$account->account_name}", [
                    'exception' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->line("Done. Refreshed: {$refreshed}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
