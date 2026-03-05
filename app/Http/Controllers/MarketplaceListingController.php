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

        // Summary stats (always uses full dataset, ignoring active filters)
        $totalListings  = MarketplaceListing::count();
        $activeCount    = MarketplaceListing::where('status', 'active')->count();
        $pausedCount    = MarketplaceListing::where('status', 'paused')->count();
        $unlinkedCount  = MarketplaceListing::unlinked()->count();
        $perAccount     = MarketplaceListing::with('marketplaceAccount')
            ->selectRaw('marketplace_account_id, count(*) as total, sum(case when status = \'active\' then 1 else 0 end) as active_count')
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
        $products = Product::orderBy('name')->get(['id', 'name', 'sku', 'price']);

        $liveData    = null;
        $quality     = null;
        $description = null;
        $apiError    = null;

        // Fetch live data from ML API
        $account = $listing->marketplaceAccount;
        if ($account && $account->credentials) {
            try {
                $service     = new MercadoLivreService($account);
                $liveData    = $service->getItem($listing->external_id);
                $quality     = $service->getItemQuality($listing->external_id);
                $description = $service->getItemDescription($listing->external_id);
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
            'liveData', 'quality', 'description', 'apiError',
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

        // Include editable attributes if provided
        if (!empty($validated['attributes'])) {
            $payload['attributes'] = collect($validated['attributes'])
                ->map(fn($value, $id) => ['id' => $id, 'value_name' => $value])
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
        $url = $request->validate(['picture_url' => 'required|url'])['picture_url'];

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $item     = $service->getItem($listing->external_id);
            $existing = collect($item['pictures'] ?? [])
                ->map(fn($p) => ['id' => $p['id']])
                ->all();
            $pictures = array_merge($existing, [['source' => $url]]);
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
                ->reject(fn($p) => $p['id'] === $pictureId)
                ->map(fn($p) => ['id' => $p['id']])
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
}
