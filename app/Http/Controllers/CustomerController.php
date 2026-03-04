<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::withCount('orders')
            ->withSum('orders', 'total')
            ->with(['orders' => fn ($q) => $q->latest()->limit(1)]);

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $customers = $query->latest()->paginate(25)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $orders = $customer->orders()
            ->with('marketplaceAccount')
            ->latest()
            ->paginate(20);

        return view('customers.show', compact('customer', 'orders'));
    }
}
