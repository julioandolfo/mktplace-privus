<?php

namespace App\Http\Controllers;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;

class MarketplaceController extends Controller
{
    public function index()
    {
        $accounts = MarketplaceAccount::with('company')->get()->keyBy(fn ($a) => $a->marketplace_type->value);

        return view('marketplaces.index', [
            'types'    => MarketplaceType::cases(),
            'accounts' => $accounts,
        ]);
    }

    public function create()
    {
        return view('marketplaces.create');
    }

    public function show(MarketplaceAccount $marketplace)
    {
        $marketplace->load('company');
        return view('marketplaces.show', compact('marketplace'));
    }

    public function edit(MarketplaceAccount $marketplace)
    {
        $marketplace->load('company');
        return view('marketplaces.edit', compact('marketplace'));
    }
}
