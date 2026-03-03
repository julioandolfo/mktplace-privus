<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\Company;
use App\Models\MarketplaceAccount;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketplaceOAuthController extends Controller
{
    public function redirect(string $type): RedirectResponse
    {
        $marketplace = MarketplaceType::from($type);

        if (! $marketplace->supportsOAuth()) {
            return redirect()->route('marketplaces.index')
                ->with('error', "{$marketplace->label()} não suporta autenticação OAuth automática.");
        }

        $clientId = SystemSetting::get('marketplaces', "{$type}_client_id");

        if (empty($clientId)) {
            return redirect()->route('settings.index')
                ->with('error', "Configure o Client ID do {$marketplace->label()} em Configurações > Marketplaces antes de conectar.");
        }

        $config = config("marketplaces.{$type}");

        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_type' => $type]);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => route('marketplaces.oauth.callback', $type),
            'state'         => $state,
        ]);

        return redirect($config['auth_url'] . '?' . $params);
    }

    public function callback(string $type, Request $request): RedirectResponse
    {
        if ($request->state !== session('oauth_state') || session('oauth_type') !== $type) {
            return redirect()->route('marketplaces.index')
                ->with('error', 'Falha na validação OAuth. Tente conectar novamente.');
        }

        if ($request->has('error')) {
            return redirect()->route('marketplaces.index')
                ->with('error', 'Autorização negada: ' . $request->error_description);
        }

        $marketplace  = MarketplaceType::from($type);
        $config       = config("marketplaces.{$type}");
        $clientId     = SystemSetting::get('marketplaces', "{$type}_client_id");
        $clientSecret = SystemSetting::get('marketplaces', "{$type}_client_secret");

        // Exchange authorization code for tokens
        $response = Http::asForm()->post($config['token_url'], [
            'grant_type'    => 'authorization_code',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $request->code,
            'redirect_uri'  => route('marketplaces.oauth.callback', $type),
        ]);

        if (! $response->successful()) {
            return redirect()->route('marketplaces.index')
                ->with('error', "Falha ao obter tokens do {$marketplace->label()}. Verifique as credenciais em Configurações > Marketplaces.");
        }

        $tokens = $response->json();

        $companyId = Company::first()?->id;
        $shopId = (string) ($tokens['user_id'] ?? $tokens['seller_id'] ?? $tokens['account_id'] ?? null);
        $accountName = $tokens['nickname'] ?? ($marketplace->label() . ' ' . now()->format('d/m/Y H:i'));
        $expiresAt = isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null;

        MarketplaceAccount::updateOrCreate(
            [
                'marketplace_type' => $type,
                'shop_id'          => $shopId ?: null,
            ],
            [
                'company_id'      => $companyId,
                'account_name'    => $accountName,
                'credentials'     => [
                    'access_token'  => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'token_type'    => $tokens['token_type'] ?? 'Bearer',
                ],
                'token_expires_at' => $expiresAt,
                'status'           => AccountStatus::Active,
                'last_error'       => null,
            ]
        );

        session()->forget(['oauth_state', 'oauth_type']);

        return redirect()->route('marketplaces.index')
            ->with('success', "{$marketplace->label()} conectado com sucesso!");
    }
}
