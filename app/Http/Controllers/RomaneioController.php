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
     * PDF das Etiquetas de Volume/Caixa com QR code por caixa.
     * Pode receber um romaneio existente ou uma lista de order_ids via query string.
     */
    public function pdfEtiquetas(Request $request, ?Romaneio $romaneio = null)
    {
        $company = Auth::user()->company;
        $labels  = [];

        if ($romaneio && $romaneio->exists) {
            // Vem de um romaneio já criado
            abort_unless($romaneio->company_id === Auth::user()->company_id, 403);
            $romaneio->load(['items.order']);

            foreach ($romaneio->items as $item) {
                for ($v = 1; $v <= $item->volumes; $v++) {
                    $labels[] = [
                        'order'        => $item->order,
                        'volume'       => $v,
                        'total_volumes'=> $item->volumes,
                        'company'      => $company,
                        'account_name' => $item->order->marketplaceAccount?->account_name ?? '',
                        'deadline'     => $item->order->meta['ml_shipping_deadline'] ?? null,
                    ];
                }
            }
        } else {
            // Vem do board de expedição: ?orders=1,2,3
            $orderIds = array_filter(explode(',', $request->query('orders', '')));

            if (empty($orderIds)) {
                abort(400, 'Nenhum pedido informado.');
            }

            $orders = Order::where('company_id', Auth::user()->company_id)
                ->whereIn('id', $orderIds)
                ->with(['marketplaceAccount'])
                ->get();

            foreach ($orders as $order) {
                $totalVols = (int) ($order->meta['expedition_volumes'] ?? 1);
                for ($v = 1; $v <= $totalVols; $v++) {
                    $labels[] = [
                        'order'        => $order,
                        'volume'       => $v,
                        'total_volumes'=> $totalVols,
                        'company'      => $company,
                        'account_name' => $order->marketplaceAccount?->account_name ?? '',
                        'deadline'     => $order->meta['ml_shipping_deadline'] ?? null,
                    ];
                }
            }
        }

        if (empty($labels)) {
            abort(404, 'Nenhuma etiqueta para gerar.');
        }

        $pdf = Pdf::loadView('pdfs.etiqueta-volume', compact('labels'))
            ->setPaper([0, 0, 283.465, 425.197], 'portrait'); // A5

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
