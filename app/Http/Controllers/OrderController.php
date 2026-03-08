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
        $order->load([
            'items.product.primaryImage',
            'items.variant',
            'company',
            'marketplaceAccount.webmaniaAccount',
            'invoices',
            'messages',
        ]);

        $unreadMessages = $order->messages->where('direction', 'received')->where('is_read', false)->count();
        $latestInvoice  = $order->invoices->sortByDesc('created_at')->first();

        return view('orders.show', compact('order', 'unreadMessages', 'latestInvoice'));
    }

    public function edit(Order $order)
    {
        $order->load(['items.product', 'items.variant']);
        return view('orders.edit', compact('order'));
    }
}
