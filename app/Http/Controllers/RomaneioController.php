<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Romaneio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RomaneioController extends Controller
{
    public function index()
    {
        $romaneios = Romaneio::where('company_id', Auth::user()->company_id)
            ->with(['createdBy', 'items'])
            ->latest()
            ->paginate(20);

        return view('romaneios.index', compact('romaneios'));
    }

    public function store(Request $request)
    {
        abort(405);
    }

    public function show(Romaneio $romaneio)
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);

        $romaneio->load(['items.order.items.product', 'createdBy', 'closedBy']);

        return view('romaneios.show', compact('romaneio'));
    }

    /**
     * PDF do Romaneio de Conferência com QR code do romaneio.
     */
    public function pdfRomaneio(Romaneio $romaneio)
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);

        $romaneio->load(['items.order.items', 'createdBy']);

        $company  = Auth::user()->company;
        $operator = Auth::user()->name;

        $pdf = Pdf::loadView('pdfs.romaneio-conferencia', compact('romaneio', 'company', 'operator'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("romaneio-{$romaneio->id}.pdf");
    }

    /**
     * PDF das Etiquetas de Volume avulsas (sem romaneio): ?orders=1,2,3
     */
    public function pdfEtiquetasAvulso(Request $request)
    {
        $company  = Auth::user()->company;
        $orderIds = array_filter(explode(',', $request->query('orders', '')));

        if (empty($orderIds)) {
            abort(400, 'Nenhum pedido informado.');
        }

        $orders = Order::whereIn('id', $orderIds)
            ->with(['marketplaceAccount'])
            ->get();

        $labels = $this->buildLabelsFromOrders($orders, $company);

        if (empty($labels)) {
            abort(404, 'Nenhuma etiqueta para gerar.');
        }

        return $this->renderEtiquetasPdf($labels);
    }

    /**
     * PDF das Etiquetas de Volume/Caixa com QR code por caixa (a partir de romaneio).
     */
    public function pdfEtiquetas(Request $request, Romaneio $romaneio)
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);

        $company = Auth::user()->company;
        $romaneio->load(['items.order.marketplaceAccount']);

        $labels = [];
        foreach ($romaneio->items as $item) {
            $mktAccount = $item->order->marketplaceAccount;
            for ($v = 1; $v <= $item->volumes; $v++) {
                $labels[] = [
                    'order'            => $item->order,
                    'volume'           => $v,
                    'total_volumes'    => $item->volumes,
                    'company'          => $company,
                    'account_name'     => $mktAccount?->account_name ?? '',
                    'marketplace_type' => $mktAccount?->marketplace_type,
                    'deadline'         => $item->order->meta['ml_shipping_deadline'] ?? null,
                ];
            }
        }

        if (empty($labels)) {
            abort(404, 'Nenhuma etiqueta para gerar.');
        }

        return $this->renderEtiquetasPdf($labels);
    }

    private function buildLabelsFromOrders($orders, $company): array
    {
        $labels = [];
        foreach ($orders as $order) {
            $mktAccount = $order->marketplaceAccount;
            $totalVols  = (int) ($order->meta['expedition_volumes'] ?? 1);
            for ($v = 1; $v <= $totalVols; $v++) {
                $labels[] = [
                    'order'            => $order,
                    'volume'           => $v,
                    'total_volumes'    => $totalVols,
                    'company'          => $company,
                    'account_name'     => $mktAccount?->account_name ?? '',
                    'marketplace_type' => $mktAccount?->marketplace_type,
                    'deadline'         => $order->meta['ml_shipping_deadline'] ?? null,
                ];
            }
        }
        return $labels;
    }

    private function renderEtiquetasPdf(array $labels)
    {
        $pdf = Pdf::loadView('pdfs.etiqueta-volume', compact('labels'))
            ->setPaper([0, 0, 283.465, 425.197], 'portrait')
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream('etiquetas-despacho.pdf');
    }

    public function scan(Request $request, Romaneio $romaneio)
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);
        abort(501, 'Use a tela de bipagem Livewire em /romaneios/{romaneio}/board');
    }

    public function close(Romaneio $romaneio)
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);

        if ($romaneio->isClosed()) {
            return back()->with('warning', 'Romaneio já está fechado.');
        }

        \App\Jobs\CloseRomaneio::dispatch($romaneio->id, Auth::id());

        return back()->with('success', 'Romaneio sendo fechado. Os pedidos serão atualizados em instantes.');
    }
}
