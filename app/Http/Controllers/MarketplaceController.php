<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\Company;
use App\Models\MarketplaceAccount;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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

        return view('marketplaces.edit', [
            'marketplace' => $marketplace,
            'companies'   => Company::orderBy('name')->get(),
            'statuses'    => AccountStatus::cases(),
            'creds'       => $creds,
            'sysClientId' => SystemSetting::get('marketplaces', "{$type}_client_id") ?? '',
            'sysSecret'   => SystemSetting::get('marketplaces', "{$type}_client_secret") ?? '',
        ]);
    }

    public function update(Request $request, MarketplaceAccount $marketplace)
    {
        $validated = $request->validate([
            'account_name'  => 'required|string|max:255',
            'shop_id'       => 'nullable|string|max:100',
            'status'        => 'required|in:' . implode(',', array_column(AccountStatus::cases(), 'value')),
            'company_id'    => 'required|exists:companies,id',
            'client_id'     => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'access_token'  => 'nullable|string|max:2000',
            'refresh_token' => 'nullable|string|max:2000',
            'api_url'       => 'nullable|url|max:500',
            'sync_interval' => 'required|integer|min:5|max:1440',
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

        $marketplace->update([
            'account_name' => $validated['account_name'],
            'shop_id'      => $request->input('shop_id') ?: null,
            'status'       => $validated['status'],
            'company_id'   => $validated['company_id'],
            'credentials'  => ! empty($credentials) ? $credentials : null,
            'settings'     => [
                'auto_sync_products' => $request->boolean('auto_sync_products'),
                'auto_sync_orders'   => $request->boolean('auto_sync_orders'),
                'auto_update_stock'  => $request->boolean('auto_update_stock'),
                'sync_interval'      => (int) $request->input('sync_interval', 30),
            ],
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
}
