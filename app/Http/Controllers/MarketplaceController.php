<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceAccount;

class MarketplaceController extends Controller
{
    public function index()
    {
        return view('marketplaces.index');
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
