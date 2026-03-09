<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #222; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a1a2e;
            padding-bottom: 0.4cm;
            margin-bottom: 0.5cm;
        }
        .header-left h1 { font-size: 16pt; font-weight: bold; color: #1a1a2e; }
        .header-left p { font-size: 8pt; color: #666; margin-top: 2px; }
        .header-right { text-align: right; }
        .header-right .rom-name { font-size: 12pt; font-weight: bold; }
        .header-right p { font-size: 8pt; color: #555; }

        .summary {
            display: flex;
            gap: 0.5cm;
            margin-bottom: 0.5cm;
        }
        .summary-box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.3cm;
            text-align: center;
        }
        .summary-box .num { font-size: 18pt; font-weight: bold; color: #1a1a2e; }
        .summary-box .lbl { font-size: 7pt; color: #888; text-transform: uppercase; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        th {
            background: #1a1a2e;
            color: white;
            padding: 0.2cm 0.3cm;
            text-align: left;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td { padding: 0.2cm 0.3cm; border-bottom: 1px solid #eee; vertical-align: top; }
        tr:nth-child(even) td { background: #f9f9f9; }

        .footer {
            margin-top: 0.5cm;
            border-top: 2px solid #1a1a2e;
            padding-top: 0.3cm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer .totals { font-size: 9pt; }
        .footer .totals strong { font-size: 11pt; }
        .footer .qr-rom img { width: 2cm; height: 2cm; }
        .footer .qr-rom p { font-size: 6pt; color: #999; text-align: center; }

        .partial-badge {
            display: inline-block;
            background: #f59e0b;
            color: white;
            font-size: 6pt;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>{{ $company->trade_name ?? $company->name }}</h1>
            <p>{{ $company->document_type === 'cnpj' ? 'CNPJ' : 'CPF' }}: {{ $company->formatted_document }}</p>
            <p>Emitido em: {{ now()->format('d/m/Y H:i') }} por {{ $operator }}</p>
        </div>
        <div class="header-right">
            <div class="rom-name">{{ $romaneio->name }}</div>
            <p>Romaneio de Conferência</p>
            <p>Criado em: {{ $romaneio->created_at->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="num">{{ $romaneio->total_orders }}</div>
            <div class="lbl">Pedidos</div>
        </div>
        <div class="summary-box">
            <div class="num">{{ $romaneio->total_volumes }}</div>
            <div class="lbl">Volumes (caixas)</div>
        </div>
        <div class="summary-box">
            <div class="num">R$ {{ number_format($romaneio->items->sum(fn($i) => $i->order->total), 2, ',', '.') }}</div>
            <div class="lbl">Valor Total</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Cidade/UF</th>
                <th>Itens</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:center">Volumes</th>
                <th>Prazo</th>
                <th style="text-align:center">✓</th>
            </tr>
        </thead>
        <tbody>
            @foreach($romaneio->items as $item)
            @php
                $order = $item->order;
                $addr  = $order->shipping_address ?? [];
                $deadline = $order->meta['ml_shipping_deadline'] ?? null;
                $isPartial = collect($item->items_detail ?? [])->sum('quantity') < $order->items->sum('quantity');
            @endphp
            <tr>
                <td>
                    <strong>{{ $order->order_number }}</strong>
                    @if($isPartial) <span class="partial-badge">PARCIAL</span> @endif
                </td>
                <td>{{ $order->customer_name }}</td>
                <td>{{ $addr['city'] ?? '—' }}{{ !empty($addr['state']) ? '/' . $addr['state'] : '' }}</td>
                <td>{{ $order->items->sum('quantity') }}</td>
                <td style="text-align:right">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                <td style="text-align:center">{{ $item->volumes }}</td>
                <td>{{ $deadline ? \Carbon\Carbon::parse($deadline)->format('d/m H:i') : '—' }}</td>
                <td style="text-align:center; font-size: 14pt;">☐</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="totals">
            <p>Total: <strong>{{ $romaneio->total_orders }} pedidos</strong> / <strong>{{ $romaneio->total_volumes }} volumes</strong></p>
        </div>
        <div class="qr-rom">
            @php
                $qrRom = (new \Picqer\Barcode\BarcodeGeneratorSVG())->getBarcode(
                    json_encode(['romaneio_id' => $romaneio->id, 'type' => 'romaneio']),
                    \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_QR_CODE
                );
            @endphp
            <div style="width:80px;height:80px">{!! $qrRom !!}</div>
            <p>ROM #{{ $romaneio->id }}</p>
        </div>
    </div>
</body>
</html>
