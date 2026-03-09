<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; }

        .page {
            width: 10cm;
            height: 15cm;
            padding: 0.5cm;
            page-break-after: always;
            border: 1px solid #ccc;
            position: relative;
        }
        .page:last-child { page-break-after: avoid; }

        .volume-badge {
            background: #1a1a2e;
            color: white;
            text-align: center;
            padding: 0.3cm 0;
            border-radius: 4px;
            margin-bottom: 0.4cm;
        }
        .volume-badge .vol-number {
            font-size: 28pt;
            font-weight: bold;
            line-height: 1;
            letter-spacing: 2px;
        }
        .volume-badge .vol-label {
            font-size: 8pt;
            opacity: 0.8;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .section { margin-bottom: 0.4cm; }
        .section-title {
            font-size: 7pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        .section-content { font-size: 9pt; line-height: 1.4; }
        .section-content strong { font-size: 10pt; }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-top: 0.3cm;
            padding-top: 0.2cm;
            border-top: 1px dashed #ccc;
            font-size: 8pt;
            color: #555;
        }

        .qr-section {
            position: absolute;
            bottom: 0.5cm;
            right: 0.5cm;
            text-align: center;
        }
        .qr-section img { width: 2.2cm; height: 2.2cm; }
        .qr-section svg { width: 2.2cm; height: 2.2cm; display: block; }
        .qr-section .qr-label { font-size: 6pt; color: #999; margin-top: 2px; }
    </style>
</head>
<body>
@foreach($labels as $label)
@php
    $order   = $label['order'];
    $vol     = $label['volume'];
    $total   = $label['total_volumes'];
    $company = $label['company'];
    $addr    = $order->shipping_address ?? [];
    $qrData  = json_encode(['order_id' => $order->id, 'vol' => $vol, 'total' => $total, 'order_number' => $order->order_number]);
    // SVG não requer extensão GD — mais compatível com ambientes Docker
    $qrSvg = (new \Picqer\Barcode\BarcodeGeneratorSVG())->getBarcode($qrData, \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_QR_CODE);
@endphp
<div class="page">
    {{-- Volume badge --}}
    <div class="volume-badge">
        <div class="vol-label">Volume</div>
        <div class="vol-number">{{ $vol }}/{{ $total }}</div>
    </div>

    {{-- Remetente --}}
    <div class="section">
        <div class="section-title">Remetente</div>
        <div class="section-content">
            <strong>{{ $company->trade_name ?? $company->name }}</strong><br>
            @if($company->document)
                {{ $company->document_type === 'cnpj' ? 'CNPJ' : 'CPF' }}: {{ $company->formatted_document }}<br>
            @endif
            @php $ca = $company->address ?? []; @endphp
            {{ $ca['street'] ?? '' }}@if(!empty($ca['number'])), {{ $ca['number'] }}@endif<br>
            {{ $ca['city'] ?? '' }}{{ !empty($ca['state']) ? ' - ' . $ca['state'] : '' }}
            @if(!empty($ca['zipcode'])) · CEP {{ $ca['zipcode'] }} @endif
        </div>
    </div>

    <div style="border-top: 2px solid #1a1a2e; margin: 0.3cm 0;"></div>

    {{-- Destinatário --}}
    <div class="section">
        <div class="section-title">Destinatário</div>
        <div class="section-content">
            <strong>{{ $order->customer_name }}</strong><br>
            @if(!empty($addr['street']))
                {{ $addr['street'] }}@if(!empty($addr['number'])), {{ $addr['number'] }}@endif<br>
            @endif
            @if(!empty($addr['complement'])) {{ $addr['complement'] }}<br> @endif
            @if(!empty($addr['neighborhood'])) {{ $addr['neighborhood'] }}<br> @endif
            {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? ' - ' . $addr['state'] : '' }}<br>
            @if(!empty($addr['zip'])) CEP {{ $addr['zip'] }} @endif
            @if(!empty($addr['zipcode'])) CEP {{ $addr['zipcode'] }} @endif
        </div>
    </div>

    {{-- Info rodapé --}}
    <div class="info-row">
        <span>{{ $order->order_number }}</span>
        <span>{{ $label['account_name'] }}</span>
        @if($label['deadline'])
        <span>Despachar até {{ \Carbon\Carbon::parse($label['deadline'])->format('d/m/Y') }}</span>
        @endif
    </div>

    {{-- QR Code (SVG inline — sem dependência de GD) --}}
    <div class="qr-section">
        <div style="width:2.2cm;height:2.2cm;overflow:hidden">{!! $qrSvg !!}</div>
        <div class="qr-label">VOL {{ $vol }}/{{ $total }}</div>
    </div>
</div>
@endforeach
</body>
</html>
