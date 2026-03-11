<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\Company;
use App\Models\MarketplaceAccount;
use App\Models\MelhorEnviosAccount;
use App\Models\SystemSetting;
use App\Models\WebmaniaAccount;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MarketplaceController extends Controller
{
    public function index()
    {
        $allAccounts = MarketplaceAccount::with('company')->get();
        $accounts    = $allAccounts->groupBy(fn ($a) => $a->marketplace_type->value);

        return view('marketplaces.index', [
            'types'       => MarketplaceType::cases(),
            'accounts'    => $accounts,
            'allAccounts' => $allAccounts,
        ]);
    }

    public function create()
    {
        return view('marketplaces.create');
    }

    public function show(MarketplaceAccount $marketplace)
    {
        $marketplace->load('company');
        $type = $marketplace->marketplace_type->value;

        return view('marketplaces.show', [
            'marketplace'     => $marketplace,
            'sysClientId'     => SystemSetting::get('marketplaces', "{$type}_client_id"),
            'sysClientSecret' => SystemSetting::get('marketplaces', "{$type}_client_secret"),
        ]);
    }

    public function edit(MarketplaceAccount $marketplace)
    {
        $marketplace->load('company');

        $type = $marketplace->marketplace_type->value;

        try {
            $creds = $marketplace->credentials ?? [];
        } catch (\Exception $e) {
            $creds = [];
        }

        $companyId = $marketplace->company_id;

        $webmaniaAccounts = WebmaniaAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $melhorEnviosAccounts = MelhorEnviosAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'name']);

        return view('marketplaces.edit', [
            'marketplace'          => $marketplace,
            'companies'            => Company::orderBy('name')->get(),
            'statuses'             => AccountStatus::cases(),
            'creds'                => $creds,
            'sysClientId'          => SystemSetting::get('marketplaces', "{$type}_client_id") ?? '',
            'sysSecret'            => SystemSetting::get('marketplaces', "{$type}_client_secret") ?? '',
            'webmaniaAccounts'     => $webmaniaAccounts,
            'melhorEnviosAccounts' => $melhorEnviosAccounts,
        ]);
    }

    public function update(Request $request, MarketplaceAccount $marketplace)
    {
        $validated = $request->validate([
            'account_name'             => 'required|string|max:255',
            'shop_id'                  => 'nullable|string|max:100',
            'status'                   => 'required|in:' . implode(',', array_column(AccountStatus::cases(), 'value')),
            'company_id'               => 'required|exists:companies,id',
            'client_id'                => 'nullable|string|max:500',
            'client_secret'            => 'nullable|string|max:500',
            'access_token'             => 'nullable|string|max:2000',
            'refresh_token'            => 'nullable|string|max:2000',
            'api_url'                  => 'nullable|url|max:500',
            'sync_interval'            => 'required|integer|min:5|max:1440',
            'nfe_method'               => 'nullable|in:native,webmaniabr,both,none',
            'webmania_account_id'      => 'nullable|integer|exists:webmania_accounts,id',
            'melhor_envios_account_id' => 'nullable|integer|exists:melhor_envios_accounts,id',
            'woo_ready_to_ship_status' => 'nullable|string|max:60',
            'woo_shipped_status'       => 'nullable|string|max:60',
            'expedition_check_packing' => 'nullable|boolean',
            'expedition_label_format'  => 'nullable|in:a4,a6',
        ]);

        // Resolve placeholder secret back to real value
        $clientSecret = $request->input('client_secret', '');
        if ($clientSecret === '••••••••') {
            $type = $marketplace->marketplace_type->value;
            try {
                $existingCreds = $marketplace->credentials ?? [];
            } catch (\Exception $e) {
                $existingCreds = [];
            }
            $clientSecret = $existingCreds['client_secret']
                ?? SystemSetting::get('marketplaces', "{$type}_client_secret")
                ?? '';
        }

        $credentials = array_filter([
            'client_id'     => $request->input('client_id', ''),
            'client_secret' => $clientSecret,
            'access_token'  => $request->input('access_token', ''),
            'refresh_token' => $request->input('refresh_token', ''),
            'api_url'       => $request->input('api_url', ''),
        ]);

        // Preserve existing OAuth tokens if the form didn't send them (OAuth type)
        if ($marketplace->marketplace_type->supportsOAuth()) {
            try {
                $existingCreds = $marketplace->credentials ?? [];
            } catch (\Exception $e) {
                $existingCreds = [];
            }
            if (empty($credentials['access_token']) && ! empty($existingCreds['access_token'])) {
                $credentials['access_token'] = $existingCreds['access_token'];
            }
            if (empty($credentials['refresh_token']) && ! empty($existingCreds['refresh_token'])) {
                $credentials['refresh_token'] = $existingCreds['refresh_token'];
            }
        }

        // Build settings: sync + expedition
        $settings = [
            'auto_sync_products'       => $request->boolean('auto_sync_products'),
            'auto_sync_orders'         => $request->boolean('auto_sync_orders'),
            'auto_update_stock'        => $request->boolean('auto_update_stock'),
            'sync_interval'            => (int) $request->input('sync_interval', 30),
            'woo_ready_to_ship_status' => $validated['woo_ready_to_ship_status'] ?? null,
            'woo_shipped_status'       => $validated['woo_shipped_status'] ?? null,
            'expedition_check_packing' => (bool) ($validated['expedition_check_packing'] ?? false),
            'expedition_label_format'  => $validated['expedition_label_format'] ?? 'a4',
        ];

        $marketplace->update([
            'account_name'             => $validated['account_name'],
            'shop_id'                  => $request->input('shop_id') ?: null,
            'status'                   => $validated['status'],
            'company_id'               => $validated['company_id'],
            'credentials'              => ! empty($credentials) ? $credentials : null,
            'settings'                 => $settings,
            'nfe_method'               => $validated['nfe_method'] ?? 'webmaniabr',
            'webmania_account_id'      => $validated['webmania_account_id'] ?? null,
            'melhor_envios_account_id' => $validated['melhor_envios_account_id'] ?? null,
        ]);

        return redirect()->route('marketplaces.index')
            ->with('success', 'Conta atualizada com sucesso.');
    }

    public function sync(Request $request, MarketplaceAccount $marketplace)
    {
        $validated = $request->validate([
            'type' => 'required|in:orders,listings',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $type = $validated['type'];
        $days = (int) ($validated['days'] ?? 7);

        try {
            $params = ['--account' => $marketplace->id, '--days' => $days];
            $exitCode = Artisan::call("marketplace:sync-{$type}", $params);
            $output   = trim(Artisan::output());

            if ($exitCode === 0 && ! str_contains($output, 'Falha') && ! str_contains($output, 'Nenhuma conta')) {
                $label = $type === 'orders' ? 'Pedidos' : 'Anúncios';
                $flash = ['success' => "{$label} sincronizados. {$output}"];
            } else {
                $flash = ['warning' => $output ?: 'Nenhum dado sincronizado. Verifique o status da conta.'];
            }
        } catch (\Throwable $e) {
            $flash = ['error' => 'Erro ao sincronizar: ' . $e->getMessage()];
        }

        if ($request->wantsJson()) {
            $key = array_key_first($flash);
            $code = $key === 'error' ? 500 : 200;
            return response()->json([$key => $flash[$key]], $code);
        }

        return redirect()->route('marketplaces.show', $marketplace)->with($flash);
    }

    public function diagnose(Request $request, MarketplaceAccount $marketplace)
    {
        if ($marketplace->marketplace_type !== MarketplaceType::MercadoLivre) {
            return back()->with('error', 'Diagnóstico disponível apenas para Mercado Livre.');
        }

        $problems  = [];
        $ok        = [];
        $mlUser    = null;

        try {
            $service = new MercadoLivreService($marketplace);
            $mlUser  = $service->getAuthenticatedUser();

            $mlUserId = (string) ($mlUser['id'] ?? '');
            $storedId = (string) ($marketplace->shop_id ?? '');

            if ($mlUserId) {
                if ($storedId === $mlUserId) {
                    $ok[] = "Token válido. Usuário ML: {$mlUser['nickname']} (ID: {$mlUserId}) — bate com o shop_id cadastrado.";
                } else {
                    $problems[] = "SHOP ID INCORRETO: token pertence ao usuário {$mlUser['nickname']} (ID: {$mlUserId}), mas o shop_id cadastrado é '{$storedId}'. Corrija o shop_id para {$mlUserId}.";
                    // Auto-fix shop_id
                    $marketplace->update(['shop_id' => $mlUserId]);
                    $ok[] = "shop_id corrigido automaticamente para {$mlUserId}.";
                }
            }

            // Check order count (no date filter)
            try {
                $testData = $service->getAuthenticatedUser(); // já feito acima
                $ok[] = "Conexão com a API do ML OK.";
            } catch (\Throwable $e) {
                $problems[] = "Erro na API: " . $e->getMessage();
            }

        } catch (\Throwable $e) {
            Log::error("MarketplaceDiagnose: {$e->getMessage()}");
            $problems[] = "Erro ao conectar com a API do ML: " . $e->getMessage();
        }

        if (! $marketplace->shop_id) {
            $problems[] = "shop_id não configurado.";
        }

        if ($marketplace->isTokenExpired()) {
            $problems[] = "Token expirado em " . ($marketplace->token_expires_at?->format('d/m/Y H:i') ?? '?');
        }

        $mlInfo = $mlUser ? " | Conta ML: {$mlUser['nickname']}" : '';

        if (count($problems) > 0) {
            $msg = "Problemas encontrados:\n• " . implode("\n• ", $problems);
            if (count($ok) > 0) {
                $msg .= "\n\nOK:\n✓ " . implode("\n✓ ", $ok);
            }
            return back()->with('warning', $msg);
        }

        $successMsg = "Diagnóstico OK{$mlInfo}. " . implode(' | ', $ok);
        return back()->with('success', $successMsg);
    }
}
