<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        return view('orders.index');
    }

    public function create()
    {
        return view('orders.create');
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'company', 'marketplaceAccount']);
        return view('orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        $order->load(['items.product', 'items.variant']);
        return view('orders.edit', compact('order'));
    }
}
