<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoices.index');
    }

    public function create()
    {
        return view('invoices.create');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['order.items', 'company']);
        return view('invoices.show', compact('invoice'));
    }
}
