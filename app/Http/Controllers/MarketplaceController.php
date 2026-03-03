<?php

namespace App\Http\Controllers;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\SystemSetting;

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
        return view('marketplaces.edit', compact('marketplace'));
    }
}
