<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Models\Product;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceListingController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketplaceListing::with(['marketplaceAccount', 'product']);

        if ($accountId = $request->input('account')) {
            $query->where('marketplace_account_id', $accountId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->input('linked') === '1') {
            $query->linked();
        } elseif ($request->input('linked') === '0') {
            $query->unlinked();
        }

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $listings  = $query->latest()->paginate(25)->withQueryString();
        $accounts  = MarketplaceAccount::orderBy('account_name')->get();

        $totalListings  = MarketplaceListing::count();
        $activeCount    = MarketplaceListing::where('status', 'active')->count();
        $pausedCount    = MarketplaceListing::where('status', 'paused')->count();
        $unlinkedCount  = MarketplaceListing::unlinked()->count();
        $perAccount     = MarketplaceListing::with('marketplaceAccount')
            ->selectRaw("marketplace_account_id, count(*) as total, sum(case when status = 'active' then 1 else 0 end) as active_count")
            ->groupBy('marketplace_account_id')
            ->get();

        return view('marketplace-listings.index', compact(
            'listings', 'accounts',
            'totalListings', 'activeCount', 'pausedCount', 'unlinkedCount', 'perAccount'
        ));
    }

    public function show(MarketplaceListing $listing)
    {
        $listing->load(['marketplaceAccount', 'product']);
        $products = Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']);

        $liveData          = null;
        $quality           = null;
        $description       = null;
        $categoryAttributes = [];
        $apiError          = null;

        $account = $listing->marketplaceAccount;
        if ($account && $account->credentials) {
            try {
                $service     = new MercadoLivreService($account);
                $liveData    = $service->getItemWithVariations($listing->external_id);
                $quality     = $service->getItemQuality($listing->external_id);
                $description = $service->getItemDescription($listing->external_id);

                // Load category attributes for the enriched form
                if (! empty($liveData['category_id'])) {
                    $categoryAttributes = $service->getCategoryAttributes($liveData['category_id']);
                }
            } catch (\Throwable $e) {
                Log::warning("ListingController show() API error: " . $e->getMessage());
                $apiError = 'Não foi possível carregar dados em tempo real. Exibindo dados locais.';
            }
        }

        // Sales stats — local DB, last 12 months
        $salesStats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereRaw("order_items.meta->>'ml_item_id' = ?", [$listing->external_id])
            ->where('orders.created_at', '>=', now()->subMonths(12))
            ->selectRaw("to_char(orders.created_at, 'YYYY-MM') as month, COUNT(*) as qty, SUM(order_items.total) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totalQty     = $salesStats->sum('qty');
        $totalRevenue = $salesStats->sum('revenue');
        $avgTicket    = $totalQty > 0 ? $totalRevenue / $totalQty : 0;

        return view('marketplace-listings.show', compact(
            'listing', 'products',
            'liveData', 'quality', 'description', 'categoryAttributes', 'apiError',
            'salesStats', 'totalQty', 'totalRevenue', 'avgTicket'
        ));
    }

    public function update(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:60',
            'price'              => 'required|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'handling_time'      => 'required|integer|min:0|max:20',
            'attributes'         => 'nullable|array',
            'shipping_width'     => 'nullable|numeric|min:0',
            'shipping_height'    => 'nullable|numeric|min:0',
            'shipping_length'    => 'nullable|numeric|min:0',
            'shipping_weight'    => 'nullable|numeric|min:0',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta do marketplace não encontrada ou sem credenciais.');
        }

        $payload = [
            'title'              => $validated['title'],
            'price'              => (float) $validated['price'],
            'available_quantity' => (int) $validated['available_quantity'],
            'shipping'           => ['handling_time' => (int) $validated['handling_time']],
        ];

        // Include dimensions if provided
        if (! empty($validated['shipping_width'])) {
            $payload['shipping']['dimensions'] = [
                'width'  => $validated['shipping_width'],
                'height' => $validated['shipping_height'] ?? 0,
                'length' => $validated['shipping_length'] ?? 0,
                'weight' => $validated['shipping_weight'] ?? 0,
            ];
        }

        // Include editable attributes if provided
        if (! empty($validated['attributes'])) {
            $payload['attributes'] = collect($validated['attributes'])
                ->map(fn ($value, $id) => ['id' => $id, 'value_name' => $value])
                ->values()
                ->all();
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateItem($listing->external_id, $payload);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao atualizar no Mercado Livre: ' . $e->getMessage());
        }

        $listing->update([
            'title'              => $validated['title'],
            'price'              => $validated['price'],
            'available_quantity' => $validated['available_quantity'],
        ]);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Anúncio atualizado com sucesso.');
    }

    public function updateVariation(Request $request, MarketplaceListing $listing, string $variationId)
    {
        $validated = $request->validate([
            'price'              => 'nullable|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $liveData = $service->getItem($listing->external_id);

            $variations = collect($liveData['variations'] ?? [])
                ->map(function ($v) use ($variationId, $validated) {
                    if ((string) $v['id'] === $variationId) {
                        $v['available_quantity'] = (int) $validated['available_quantity'];
                        if (! empty($validated['price'])) {
                            $v['price'] = (float) $validated['price'];
                        }
                    }
                    return $v;
                })
                ->values()
                ->all();

            $service->updateItem($listing->external_id, ['variations' => $variations]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao atualizar variação: ' . $e->getMessage());
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Variação atualizada com sucesso.');
    }

    public function updateDescription(Request $request, MarketplaceListing $listing)
    {
        $text = $request->validate(['description' => 'required|string'])['description'];

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateDescription($listing->external_id, $text);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao salvar descrição: ' . $e->getMessage());
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Descrição atualizada com sucesso.');
    }

    public function addPicture(Request $request, MarketplaceListing $listing)
    {
        $request->validate([
            'picture_url'  => 'nullable|url',
            'picture_file' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if (! $request->hasFile('picture_file') && ! $request->input('picture_url')) {
            return back()->with('error', 'Informe uma URL ou faça upload de um arquivo.');
        }

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $item     = $service->getItem($listing->external_id);
            $existing = collect($item['pictures'] ?? [])
                ->map(fn ($p) => ['id' => $p['id']])
                ->all();

            if ($request->hasFile('picture_file')) {
                // Upload file directly to ML
                $pictureId = $service->uploadPicture($request->file('picture_file'));
                $pictures  = array_merge($existing, [['id' => $pictureId]]);
            } else {
                // Add by URL
                $pictures = array_merge($existing, [['source' => $request->input('picture_url')]]);
            }

            $service->updateItem($listing->external_id, ['pictures' => $pictures]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao adicionar imagem: ' . $e->getMessage());
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Imagem adicionada com sucesso.');
    }

    public function removePicture(Request $request, MarketplaceListing $listing, string $pictureId)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $item     = $service->getItem($listing->external_id);
            $pictures = collect($item['pictures'] ?? [])
                ->reject(fn ($p) => $p['id'] === $pictureId)
                ->map(fn ($p) => ['id' => $p['id']])
                ->values()
                ->all();
            $service->updateItem($listing->external_id, ['pictures' => $pictures]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao remover imagem: ' . $e->getMessage());
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Imagem removida com sucesso.');
    }

    public function toggleStatus(MarketplaceListing $listing)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta do marketplace não encontrada ou sem credenciais.');
        }

        $newStatus = $listing->status === 'active' ? 'paused' : 'active';

        try {
            $service = new MercadoLivreService($account);
            $service->updateItem($listing->external_id, ['status' => $newStatus]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao alterar status no Mercado Livre: ' . $e->getMessage());
        }

        $listing->update(['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'ativado' : 'pausado';
        return redirect()->route('listings.show', $listing)
            ->with('success', "Anúncio {$label} com sucesso.");
    }

    public function linkProduct(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'product_id'       => 'required|exists:products,id',
            'product_quantity' => 'required|integer|min:1',
        ]);

        $listing->update([
            'product_id'       => $validated['product_id'],
            'product_quantity' => $validated['product_quantity'],
        ]);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Produto vinculado com sucesso.');
    }

    public function unlinkProduct(MarketplaceListing $listing)
    {
        $listing->update(['product_id' => null, 'product_quantity' => 1]);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Vínculo removido.');
    }

    public function createProduct(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'price'            => 'required|numeric|min:0',
            'sku'              => 'nullable|string|max:100',
            'product_quantity' => 'required|integer|min:1',
        ]);

        $product = Product::create([
            'name'   => $validated['name'],
            'price'  => $validated['price'],
            'sku'    => $validated['sku'] ?: ($listing->meta['seller_sku'] ?? $listing->external_id),
            'status' => ProductStatus::Active,
            'meta'   => ['created_from_listing' => $listing->external_id],
        ]);

        $listing->update([
            'product_id'       => $product->id,
            'product_quantity' => $validated['product_quantity'],
        ]);

        return redirect()->route('listings.show', $listing)
            ->with('success', "Produto \"{$product->name}\" criado e vinculado.");
    }

    public function publish(Request $request)
    {
        $validated = $request->validate([
            'marketplace_account_id' => 'required|exists:marketplace_accounts,id',
            'product_id'             => 'required|exists:products,id',
            'title'                  => 'required|string|max:60',
            'category_id'            => 'required|string',
            'listing_type_id'        => 'required|string|in:gold_special,gold_pro,free',
            'price'                  => 'required|numeric|min:0',
            'available_quantity'     => 'required|integer|min:1',
            'description'            => 'nullable|string',
            'handling_time'          => 'required|integer|min:0|max:20',
            'condition'              => 'required|string|in:new,used,not_specified',
            'attributes'             => 'nullable|array',
            'pictures'               => 'nullable|array',
            'pictures.*'             => 'url',
        ]);

        $account = MarketplaceAccount::findOrFail($validated['marketplace_account_id']);

        if (! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais configuradas.');
        }

        try {
            $service = new MercadoLivreService($account);

            $payload = [
                'title'              => $validated['title'],
                'category_id'        => $validated['category_id'],
                'price'              => (float) $validated['price'],
                'available_quantity' => (int) $validated['available_quantity'],
                'buying_mode'        => 'buy_it_now',
                'listing_type_id'    => $validated['listing_type_id'],
                'condition'          => $validated['condition'],
                'currency_id'        => 'BRL',
                'shipping'           => [
                    'mode'          => 'me2',
                    'handling_time' => (int) $validated['handling_time'],
                ],
            ];

            if (! empty($validated['attributes'])) {
                $payload['attributes'] = collect($validated['attributes'])
                    ->map(fn ($value, $id) => ['id' => $id, 'value_name' => $value])
                    ->values()
                    ->all();
            }

            if (! empty($validated['pictures'])) {
                $payload['pictures'] = array_map(fn ($url) => ['source' => $url], $validated['pictures']);
            }

            $item = $service->publishItem($payload);

            if (empty($item['id'])) {
                return back()->with('error', 'Erro ao publicar: resposta inválida do ML.');
            }

            // Update description if provided
            if (! empty($validated['description'])) {
                try {
                    $service->updateDescription($item['id'], $validated['description']);
                } catch (\Throwable $e) {
                    Log::warning("Publish: erro ao salvar descrição: " . $e->getMessage());
                }
            }

            // Create local listing record
            $listing = MarketplaceListing::create([
                'marketplace_account_id' => $account->id,
                'external_id'            => $item['id'],
                'product_id'             => $validated['product_id'],
                'product_quantity'       => 1,
                'title'                  => $validated['title'],
                'price'                  => $validated['price'],
                'available_quantity'     => $validated['available_quantity'],
                'status'                 => 'active',
                'meta'                   => [
                    'ml_item_id'      => $item['id'],
                    'ml_status'       => 'active',
                    'ml_permalink'    => $item['permalink'] ?? null,
                    'category_id'     => $validated['category_id'],
                    'listing_type_id' => $validated['listing_type_id'],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error("Publish listing error: " . $e->getMessage());
            return back()->with('error', 'Erro ao publicar no Mercado Livre: ' . $e->getMessage());
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Anúncio publicado com sucesso no Mercado Livre!');
    }

    public function publishForm(Request $request)
    {
        $accounts = MarketplaceAccount::active()->orderBy('account_name')->get();
        $products = Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']);
        $preselectedProduct = $request->input('product_id')
            ? Product::find($request->input('product_id'))
            : null;

        return view('marketplace-listings.publish', compact('accounts', 'products', 'preselectedProduct'));
    }

    public function searchCategories(Request $request)
    {
        $query      = $request->validate(['q' => 'required|string|min:2'])['q'];
        $accountId  = $request->input('account_id');
        $account    = MarketplaceAccount::findOrFail($accountId);

        try {
            $service    = new MercadoLivreService($account);
            $categories = $service->searchCategories($query);
        } catch (\Throwable $e) {
            $categories = [];
        }

        return response()->json($categories);
    }

    public function getCategoryAttributes(Request $request)
    {
        $categoryId = $request->validate(['category_id' => 'required|string'])['category_id'];
        $accountId  = $request->input('account_id');
        $account    = MarketplaceAccount::findOrFail($accountId);

        try {
            $service    = new MercadoLivreService($account);
            $attributes = $service->getCategoryAttributes($categoryId);
        } catch (\Throwable $e) {
            $attributes = [];
        }

        return response()->json($attributes);
    }
}
