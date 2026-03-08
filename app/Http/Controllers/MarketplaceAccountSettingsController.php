<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceAccount;
use App\Models\MelhorEnviosAccount;
use App\Models\WebmaniaAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceAccountSettingsController extends Controller
{
    public function index()
    {
        $accounts = MarketplaceAccount::where('company_id', Auth::user()->company_id)
            ->with(['webmaniaAccount', 'melhorEnviosAccount'])
            ->orderBy('account_name')
            ->get();

        $webmaniaAccounts    = WebmaniaAccount::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $melhorEnviosAccounts = MelhorEnviosAccount::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->get(['id', 'name']);

        return view('settings.accounts', compact('accounts', 'webmaniaAccounts', 'melhorEnviosAccounts'));
    }

    /**
     * Atualiza vínculos e configurações de uma conta de marketplace.
     * Inclui: webmania_account_id, melhor_envios_account_id,
     *         woo_ready_to_ship_status, woo_shipped_status,
     *         expedition_check_packing (bool),
     *         expedition_label_format (a4/a6).
     */
    public function update(Request $request, MarketplaceAccount $account)
    {
        abort_unless($account->company_id === Auth::user()->company_id, 403);

        $validated = $request->validate([
            'webmania_account_id'      => 'nullable|integer|exists:webmania_accounts,id',
            'melhor_envios_account_id' => 'nullable|integer|exists:melhor_envios_accounts,id',
            'woo_ready_to_ship_status' => 'nullable|string|max:60',
            'woo_shipped_status'       => 'nullable|string|max:60',
            'expedition_check_packing' => 'nullable|boolean',
            'expedition_label_format'  => 'nullable|in:a4,a6',
        ]);

        // Vínculo direto na tabela
        $account->update([
            'webmania_account_id'      => $validated['webmania_account_id'] ?? null,
            'melhor_envios_account_id' => $validated['melhor_envios_account_id'] ?? null,
        ]);

        // Configurações de expedição/WooCommerce ficam em settings JSONB
        $settings = $account->settings ?? [];
        $settings['woo_ready_to_ship_status'] = $validated['woo_ready_to_ship_status'] ?? null;
        $settings['woo_shipped_status']       = $validated['woo_shipped_status'] ?? null;
        $settings['expedition_check_packing'] = (bool) ($validated['expedition_check_packing'] ?? false);
        $settings['expedition_label_format']  = $validated['expedition_label_format'] ?? 'a4';
        $account->update(['settings' => $settings]);

        return back()->with('success', "Conta \"{$account->account_name}\" atualizada.");
    }
}
