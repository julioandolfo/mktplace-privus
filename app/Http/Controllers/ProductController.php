<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return view('products.index');
    }

    public function create()
    {
        return view('products.create');
    }

    public function edit(Product $product)
    {
        $product->load(['variants', 'images', 'kitItems.componentProduct', 'kitItems.componentVariant']);
        return view('products.edit', compact('product'));
    }
}
