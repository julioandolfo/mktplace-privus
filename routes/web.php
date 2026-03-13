<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceListingController;
use App\Http\Controllers\MarketplaceOAuthController;
use App\Http\Controllers\MarketplaceWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Marketplace Webhooks — públicos, sem autenticação (chamados pelos marketplaces)
Route::post('/webhooks/{type}', [MarketplaceWebhookController::class, 'handle'])->name('webhooks.marketplace');

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

    // DEBUG: temporary — remove after fixing
    Route::get('/debug-upload', [CompanyController::class, 'debugUploadForm']);
    Route::post('/debug-upload', [CompanyController::class, 'debugUploadPost']);
    Route::get('/debug-log', [CompanyController::class, 'debugLog']);

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

    // ─── Designer Module ────────────────────────────────────────────────────────
    Route::prefix('designer')->middleware('role:designer,admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\DesignEditorController::class, 'board'])->name('designer.index');
        Route::get('/{assignment}/edit', [\App\Http\Controllers\DesignEditorController::class, 'edit'])->name('designer.edit');
        Route::post('/{assignment}/save', [\App\Http\Controllers\DesignEditorController::class, 'save'])->name('designer.save');
        Route::post('/{assignment}/complete', [\App\Http\Controllers\DesignEditorController::class, 'complete'])->name('designer.complete');
        Route::post('/{assignment}/start', [\App\Http\Controllers\DesignEditorController::class, 'start'])->name('designer.start');

        // Arquivos
        Route::post('/{assignment}/files', [\App\Http\Controllers\DesignFileController::class, 'store'])->name('designer.files.store');
        Route::delete('/files/{file}', [\App\Http\Controllers\DesignFileController::class, 'destroy'])->name('designer.files.destroy');

        // Mockup IA
        Route::post('/{assignment}/ai-mockup', [\App\Http\Controllers\AiMockupController::class, 'generate'])->name('designer.ai-mockup');
        Route::post('/{assignment}/ai-mockup/approve', [\App\Http\Controllers\AiMockupController::class, 'approve'])->name('designer.ai-mockup.approve');
    });

    // ─── Configurações de Designers (admin only) ────────────────────────────────
    Route::prefix('settings/designers')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\DesignerSettingsController::class, 'index'])->name('settings.designers.index');
        Route::put('/', [\App\Http\Controllers\DesignerSettingsController::class, 'update'])->name('settings.designers.update');
        Route::post('/users', [\App\Http\Controllers\DesignerSettingsController::class, 'inviteDesigner'])->name('settings.designers.invite');
        Route::patch('/users/{user}/toggle', [\App\Http\Controllers\DesignerSettingsController::class, 'toggleDesigner'])->name('settings.designers.toggle');
        Route::delete('/users/{user}', [\App\Http\Controllers\DesignerSettingsController::class, 'removeDesigner'])->name('settings.designers.remove');
    });

    // Expedition
    Route::get('/expedition', function () {
        return view('expedition.index');
    })->name('expedition.index');

    Route::get('/expedition/bonuses', fn () => view('expedition.bonuses'))->name('expedition.bonuses');

    // Compras
    Route::get('/purchases', fn () => view('purchases.index'))->name('purchases.index');
    Route::get('/purchases/suppliers', fn () => view('purchases.suppliers'))->name('purchases.suppliers');

    // Packing (conferência de embalagem)
    Route::get('/orders/{order}/pack', \App\Livewire\Orders\PackingScreen::class)->name('orders.pack');
    Route::post('/orders/{order}/mark-packed', [\App\Http\Controllers\OrderDispatchController::class, 'markPacked'])->name('orders.mark-packed');
    Route::post('/orders/{order}/partial-dispatch', [\App\Http\Controllers\OrderDispatchController::class, 'partial'])->name('orders.partial-dispatch');
    Route::post('/orders/{order}/items/{item}/cancel-remaining', [\App\Http\Controllers\OrderDispatchController::class, 'cancelRemaining'])->name('orders.cancel-remaining');

    // Romaneios
    Route::get('/romaneios', [\App\Http\Controllers\RomaneioController::class, 'index'])->name('romaneios.index');
    Route::post('/romaneios', [\App\Http\Controllers\RomaneioController::class, 'store'])->name('romaneios.store');
    // Etiquetas avulsas (sem romaneio): ?orders=1,2,3 — deve vir ANTES de {romaneio}
    Route::get('/romaneios/etiquetas-avulso', [\App\Http\Controllers\RomaneioController::class, 'pdfEtiquetasAvulso'])->name('romaneios.etiquetas-avulso');
    Route::get('/romaneios/{romaneio}', [\App\Http\Controllers\RomaneioController::class, 'show'])->name('romaneios.show');
    Route::get('/romaneios/{romaneio}/pdf/romaneio', [\App\Http\Controllers\RomaneioController::class, 'pdfRomaneio'])->name('romaneios.pdf.romaneio');
    Route::get('/romaneios/{romaneio}/pdf/etiquetas', [\App\Http\Controllers\RomaneioController::class, 'pdfEtiquetas'])->name('romaneios.pdf.etiquetas');
    Route::post('/romaneios/{romaneio}/scan', [\App\Http\Controllers\RomaneioController::class, 'scan'])->name('romaneios.scan');
    Route::post('/romaneios/{romaneio}/close', [\App\Http\Controllers\RomaneioController::class, 'close'])->name('romaneios.close');

    // Romaneio board — tela de bipagem
    Route::get('/romaneios/{romaneio}/board', \App\Livewire\Romaneios\RomaneioBoard::class)->name('romaneios.board');
    Route::get('/romaneios/{romaneio}/pdf/etiquetas-avulso', [\App\Http\Controllers\RomaneioController::class, 'pdfEtiquetas'])->name('romaneios.pdf.etiquetas-avulso');

    // NF-e
    Route::post('/orders/{order}/invoice/emit', [\App\Http\Controllers\ShippingController::class, 'emitInvoice'])->name('orders.invoice.emit');

    // Artwork por item
    Route::patch('/orders/{order}/items/{item}/artwork', [\App\Http\Controllers\OrderItemArtworkController::class, 'update'])->name('orders.items.artwork');

    // Etiquetas oficiais ML
    Route::get('/orders/{order}/ml-label', [\App\Http\Controllers\ShippingController::class, 'mlLabel'])->name('orders.ml-label');
    Route::post('/orders/ml-labels-batch', [\App\Http\Controllers\ShippingController::class, 'mlLabelsBatch'])->name('orders.ml-labels-batch');

    // Melhor Envios
    Route::post('/orders/{order}/shipping/quote', [\App\Http\Controllers\ShippingController::class, 'quote'])->name('orders.shipping.quote');
    Route::post('/orders/{order}/shipping/purchase', [\App\Http\Controllers\ShippingController::class, 'purchase'])->name('orders.shipping.purchase');
    Route::post('/webhooks/melhor-envios', [\App\Http\Controllers\ShippingController::class, 'webhook'])->name('webhooks.melhor-envios');

    // Marketplaces
    Route::get('/marketplaces', [MarketplaceController::class, 'index'])->name('marketplaces.index');
    Route::get('/marketplaces/create', [MarketplaceController::class, 'create'])->name('marketplaces.create');
    Route::get('/marketplaces/{marketplace}', [MarketplaceController::class, 'show'])->name('marketplaces.show');
    Route::get('/marketplaces/{marketplace}/edit', [MarketplaceController::class, 'edit'])->name('marketplaces.edit');
    Route::put('/marketplaces/{marketplace}', [MarketplaceController::class, 'update'])->name('marketplaces.update');
    Route::post('/marketplaces/{marketplace}/sync', [MarketplaceController::class, 'sync'])->name('marketplaces.sync');
    Route::post('/marketplaces/{marketplace}/diagnose', [MarketplaceController::class, 'diagnose'])->name('marketplaces.diagnose');

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

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Marketplace Listings (Anuncios)
    Route::get('/listings', [MarketplaceListingController::class, 'index'])->name('listings.index');
    Route::post('/listings/bulk-action', [MarketplaceListingController::class, 'bulkAction'])->name('listings.bulk-action');
    Route::post('/listings/sync-quality', [MarketplaceListingController::class, 'syncQuality'])->name('listings.sync-quality');
    Route::get('/listings/publish', [MarketplaceListingController::class, 'publishForm'])->name('listings.publish-form');
    Route::post('/listings/publish', [MarketplaceListingController::class, 'publish'])->name('listings.publish');
    Route::get('/listings/categories/search', [MarketplaceListingController::class, 'searchCategories'])->name('listings.search-categories');
    Route::get('/listings/categories/attributes', [MarketplaceListingController::class, 'getCategoryAttributes'])->name('listings.category-attributes');
    Route::get('/listings/{listing}', [MarketplaceListingController::class, 'show'])->name('listings.show');
    Route::put('/listings/{listing}', [MarketplaceListingController::class, 'update'])->name('listings.update');
    Route::post('/listings/{listing}/toggle-status', [MarketplaceListingController::class, 'toggleStatus'])->name('listings.toggle-status');
    Route::post('/listings/{listing}/update-description', [MarketplaceListingController::class, 'updateDescription'])->name('listings.update-description');
    Route::post('/listings/{listing}/ai-description', [MarketplaceListingController::class, 'generateDescriptionAi'])->name('listings.ai-description');
    Route::post('/listings/{listing}/ai-image', [MarketplaceListingController::class, 'generateImageAi'])->name('listings.ai-image');
    Route::post('/listings/{listing}/ai-improve', [MarketplaceListingController::class, 'improveWithAi'])->name('listings.ai-improve');
    Route::post('/listings/{listing}/listing-type', [MarketplaceListingController::class, 'updateListingType'])->name('listings.update-listing-type');
    Route::post('/listings/{listing}/shipping', [MarketplaceListingController::class, 'updateShipping'])->name('listings.update-shipping');
    Route::post('/listings/{listing}/fiscal-data', [MarketplaceListingController::class, 'updateFiscalData'])->name('listings.update-fiscal-data');
    Route::post('/listings/{listing}/pictures', [MarketplaceListingController::class, 'addPicture'])->name('listings.add-picture');
    Route::delete('/listings/{listing}/pictures/{pictureId}', [MarketplaceListingController::class, 'removePicture'])->name('listings.remove-picture');
    Route::post('/listings/{listing}/link-product', [MarketplaceListingController::class, 'linkProduct'])->name('listings.link-product');
    Route::post('/listings/{listing}/create-product', [MarketplaceListingController::class, 'createProduct'])->name('listings.create-product');
    Route::delete('/listings/{listing}/unlink-product', [MarketplaceListingController::class, 'unlinkProduct'])->name('listings.unlink-product');
    Route::put('/listings/{listing}/variations/{variationId}', [MarketplaceListingController::class, 'updateVariation'])->name('listings.update-variation');
    Route::delete('/listings/{listing}/variations/{variationId}', [MarketplaceListingController::class, 'deleteVariation'])->name('listings.delete-variation');
    Route::post('/listings/{listing}/variations', [MarketplaceListingController::class, 'addVariation'])->name('listings.add-variation');
    // Kits
    Route::get('/listings/{listing}/kit', [MarketplaceListingController::class, 'kitForm'])->name('listings.kit-form');
    Route::post('/listings/{listing}/kit/multipack', [MarketplaceListingController::class, 'storeMultipack'])->name('listings.store-multipack');
    Route::post('/listings/{listing}/kit/combo', [MarketplaceListingController::class, 'storeCombo'])->name('listings.store-combo');
    Route::get('/listings/{listing}/kit/search-components', [MarketplaceListingController::class, 'searchKitComponents'])->name('listings.kit-search-components');
    // Promotions
    Route::get('/listings/{listing}/promotions', [MarketplaceListingController::class, 'getPromotions'])->name('listings.promotions');
    Route::post('/listings/{listing}/promotions', [MarketplaceListingController::class, 'storePromotion'])->name('listings.store-promotion');
    Route::delete('/listings/{listing}/promotions', [MarketplaceListingController::class, 'deletePromotion'])->name('listings.delete-promotion');

    // Logs
    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
    Route::post('/settings/ai/test', [SettingsController::class, 'testAiConnection'])->name('settings.ai.test');
    Route::get('/settings/ai/models', [SettingsController::class, 'aiModels'])->name('settings.ai.models');

    // Configurações — Melhor Envios
    Route::resource('settings/melhor-envios', \App\Http\Controllers\MelhorEnviosAccountController::class)
        ->names('settings.me')
        ->parameters(['melhor-envios' => 'melhorEnvio']);
    Route::get('/auth/melhor-envios/{melhorEnvio}/connect', [\App\Http\Controllers\MelhorEnviosAccountController::class, 'connect'])->name('me.connect');
    Route::get('/auth/melhor-envios/callback', [\App\Http\Controllers\MelhorEnviosAccountController::class, 'callback'])->name('me.callback');

    // Configurações — Webmaniabr
    Route::get('/settings/webmania', [\App\Http\Controllers\WebmaniaAccountController::class, 'index'])->name('settings.webmania.index');
    Route::get('/settings/webmania/create', [\App\Http\Controllers\WebmaniaAccountController::class, 'create'])->name('settings.webmania.create');
    Route::post('/settings/webmania', [\App\Http\Controllers\WebmaniaAccountController::class, 'store'])->name('settings.webmania.store');
    Route::get('/settings/webmania/{account}/edit', [\App\Http\Controllers\WebmaniaAccountController::class, 'edit'])->name('settings.webmania.edit');
    Route::put('/settings/webmania/{account}', [\App\Http\Controllers\WebmaniaAccountController::class, 'update'])->name('settings.webmania.update');
    Route::delete('/settings/webmania/{account}', [\App\Http\Controllers\WebmaniaAccountController::class, 'destroy'])->name('settings.webmania.destroy');

    // Redirect old settings/accounts to marketplaces (config moved to marketplace edit page)
    Route::get('/settings/accounts', fn () => redirect()->route('marketplaces.index'))->name('settings.accounts.index');

    // Configurações — Usuarios do Sistema
    Route::prefix('settings/users')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserSettingsController::class, 'index'])->name('settings.users.index');
        Route::post('/', [\App\Http\Controllers\UserSettingsController::class, 'store'])->name('settings.users.store');
        Route::put('/{user}', [\App\Http\Controllers\UserSettingsController::class, 'update'])->name('settings.users.update');
        Route::delete('/{user}', [\App\Http\Controllers\UserSettingsController::class, 'destroy'])->name('settings.users.destroy');
    });

    // Configurações — Operadores de Expedição
    Route::get('/settings/expedition-operators', fn () => view('settings.expedition-operators'))->name('settings.operators.index');

    // Configurações — Bonificação Expedição
    Route::get('/settings/expedition-bonus', fn () => view('settings.expedition-bonus'))->name('settings.bonus.index');
});

require __DIR__.'/auth.php';
