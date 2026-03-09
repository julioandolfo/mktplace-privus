<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; }

        .page {
            width: 10cm;
            min-height: 15cm;
            padding: 0.4cm;
            overflow: hidden;
        }

        .volume-badge {
            background: #1a1a2e;
            color: white;
            text-align: center;
            padding: 0.25cm 0;
            border-radius: 4px;
            margin-bottom: 0.35cm;
        }
        .volume-badge .vol-label {
            font-size: 7pt;
            opacity: 0.8;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .volume-badge .vol-number {
            font-size: 22pt;
            font-weight: bold;
            line-height: 1.1;
            letter-spacing: 2px;
        }

        .section { margin-bottom: 0.35cm; }
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

        .divider { border-top: 2px solid #1a1a2e; margin: 0.25cm 0; }

        .info-row {
            display: table;
            width: 100%;
            margin-top: 0.25cm;
            padding-top: 0.2cm;
            border-top: 1px dashed #ccc;
            font-size: 7.5pt;
            color: #555;
        }
        .info-row span {
            display: table-cell;
            width: 33%;
        }
        .info-row span:last-child { text-align: right; }

        .barcode-row {
            margin-top: 0.3cm;
            text-align: center;
            padding-top: 0.2cm;
            border-top: 1px solid #eee;
        }
        .barcode-row img {
            display: block;
            margin: 0 auto;
            width: 7cm;
            height: 1cm;
        }
        .qr-row {
            margin-top: 0.2cm;
            text-align: center;
        }
        .qr-row img {
            display: block;
            margin: 0 auto;
            width: 2.2cm;
            height: 2.2cm;
        }
        .bc-label {
            font-size: 7pt;
            color: #444;
            margin-top: 2px;
            font-family: monospace;
            letter-spacing: 1px;
        }

        .page-break { page-break-after: always; }
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

    $barcodeData = $order->order_number . '-V' . $vol;

    // Barcode Code128 como data URI (DomPDF renderiza <img> melhor que SVG inline)
    $barGen     = new \Picqer\Barcode\BarcodeGeneratorSVG();
    $barcodeSvg = $barGen->getBarcode($barcodeData, $barGen::TYPE_CODE_128, 2, 60);
    $barcodeUri = 'data:image/svg+xml;base64,' . base64_encode($barcodeSvg);

    // QR Code se a biblioteca estiver disponível (instalada no Dockerfile via composer require)
    $qrUri = null;
    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
        try {
            $qrOptions = new \chillerlan\QRCode\QROptions;
            $qrOptions->outputType    = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
            $qrOptions->scale         = 6;
            $qrOptions->outputBase64  = false;
            $qrPng = (new \chillerlan\QRCode\QRCode($qrOptions))->render($barcodeData);
            $qrUri = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable $e) {
            // Biblioteca não disponível ou GD não instalado — ignora QR
        }
    }
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
        </div>
    </div>

    <div class="divider"></div>

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

    {{-- Barcode Code128 --}}
    <div class="barcode-row">
        <img src="{{ $barcodeUri }}" alt="{{ $barcodeData }}">
        <div class="bc-label">{{ $barcodeData }}</div>
    </div>

    {{-- QR Code (quando biblioteca disponível) --}}
    @if($qrUri)
    <div class="qr-row">
        <img src="{{ $qrUri }}" alt="QR {{ $barcodeData }}">
    </div>
    @endif
</div>
@if(!$loop->last)
<div class="page-break"></div>
@endif
@endforeach
</body>
</html>
