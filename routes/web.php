<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceOAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Companies
    Route::resource('companies', CompanyController::class);

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');

    // Production
    Route::get('/production', function () {
        return view('production.index');
    })->name('production.index');

    // Expedition
    Route::get('/expedition', function () {
        return view('expedition.index');
    })->name('expedition.index');

    // Marketplaces
    Route::get('/marketplaces', [MarketplaceController::class, 'index'])->name('marketplaces.index');
    Route::get('/marketplaces/create', [MarketplaceController::class, 'create'])->name('marketplaces.create');
    Route::get('/marketplaces/{marketplace}', [MarketplaceController::class, 'show'])->name('marketplaces.show');
    Route::get('/marketplaces/{marketplace}/edit', [MarketplaceController::class, 'edit'])->name('marketplaces.edit');

    // Marketplaces OAuth
    Route::get('/marketplaces/oauth/{type}/redirect', [MarketplaceOAuthController::class, 'redirect'])->name('marketplaces.oauth.redirect');
    Route::get('/marketplaces/oauth/{type}/callback', [MarketplaceOAuthController::class, 'callback'])->name('marketplaces.oauth.callback');

    // Invoices (NF-e)
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    // Kits
    Route::get('/kits', function () {
        return view('kits.index');
    })->name('kits.index');

    // Stock
    Route::get('/stock', function () {
        return view('stock.index');
    })->name('stock.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
