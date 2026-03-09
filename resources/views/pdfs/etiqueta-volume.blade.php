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

        /* ── Volume badge ── */
        .volume-badge {
            background: #1a1a2e;
            color: white;
            text-align: center;
            padding: 0.2cm 0;
            border-radius: 4px 4px 0 0;
            margin-bottom: 0;
        }
        .volume-badge .vol-label {
            font-size: 6.5pt;
            opacity: 0.75;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .volume-badge .vol-number {
            font-size: 20pt;
            font-weight: bold;
            line-height: 1.1;
            letter-spacing: 2px;
        }

        /* ── Marketplace badge ── */
        .mkt-badge {
            display: table;
            width: 100%;
            border-radius: 0 0 4px 4px;
            margin-bottom: 0.3cm;
            padding: 0.12cm 0.25cm;
        }
        .mkt-badge-logo { display: table-cell; vertical-align: middle; width: 22pt; }
        .mkt-badge-logo svg { width: 18pt; height: 18pt; }
        .mkt-badge-name {
            display: table-cell;
            vertical-align: middle;
            font-size: 10pt;
            font-weight: bold;
            padding-left: 0.15cm;
            letter-spacing: 0.5px;
        }
        .mkt-badge-account {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 7pt;
            opacity: 0.75;
        }

        /* ── Seções ── */
        .section { margin-bottom: 0.3cm; }
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

        .divider { border-top: 2px solid #1a1a2e; margin: 0.2cm 0; }

        /* ── Linha de info ── */
        .info-row {
            display: table;
            width: 100%;
            margin-top: 0.2cm;
            padding-top: 0.15cm;
            border-top: 1px dashed #ccc;
            font-size: 7pt;
            color: #555;
        }
        .info-row .c1 { display: table-cell; width: 40%; }
        .info-row .c2 { display: table-cell; width: 60%; text-align: right; }

        /* ── Barcode ── */
        .barcode-row {
            margin-top: 0.25cm;
            text-align: center;
            padding-top: 0.15cm;
            border-top: 1px solid #eee;
        }
        .barcode-row img {
            display: block;
            margin: 0 auto;
            width: 7.5cm;
            height: 0.9cm;
        }
        .qr-row {
            margin-top: 0.15cm;
            text-align: center;
        }
        .qr-row img {
            display: block;
            margin: 0 auto;
            width: 2cm;
            height: 2cm;
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
    $mkt     = $label['marketplace_type'] ?? null; // \App\Enums\MarketplaceType

    // ── Cores e nome do marketplace ──
    $mktColor   = $mkt?->color()           ?? '#4B5563';
    $mktLabel   = $mkt?->label()           ?? ($label['account_name'] ?: 'Marketplace');
    $mktAccount = $label['account_name']   ?? '';
    $mktTextClr = in_array($mkt?->value, ['mercado_livre', 'amazon']) ? '#1a1a2e' : '#FFFFFF';

    // SVG logos inline — simples, compatíveis com DomPDF
    $mktLogoSvg = match($mkt?->value) {
        'mercado_livre' => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#FFE600"/>
              <text x="20" y="15" text-anchor="middle" font-size="10" font-weight="bold" font-family="Arial" fill="#2d3436">ML</text>
              <text x="20" y="28" text-anchor="middle" font-size="6.5" font-family="Arial" fill="#2d3436">Mercado</text>
              <text x="20" y="36" text-anchor="middle" font-size="6.5" font-family="Arial" fill="#2d3436">Livre</text>
            </svg>',
        'shopee' => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#EE4D2D"/>
              <ellipse cx="20" cy="16" rx="9" ry="5" fill="none" stroke="white" stroke-width="2.5"/>
              <rect x="14" y="14" width="12" height="14" rx="2" fill="white"/>
              <rect x="17" y="17" width="6" height="8" rx="1" fill="#EE4D2D"/>
            </svg>',
        'amazon' => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#FF9900"/>
              <text x="20" y="18" text-anchor="middle" font-size="9" font-weight="bold" font-family="Arial" fill="#1a1a2e">amazon</text>
              <path d="M8 27 Q20 34 32 27" stroke="#1a1a2e" stroke-width="2.5" fill="none" stroke-linecap="round"/>
              <polygon points="30,24 34,27 30,30" fill="#1a1a2e"/>
            </svg>',
        'woocommerce' => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#7F54B3"/>
              <text x="20" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial" fill="white">Woo</text>
              <text x="20" y="28" text-anchor="middle" font-size="7" font-family="Arial" fill="white">Commerce</text>
            </svg>',
        'tiktok' => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#010101"/>
              <text x="20" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial" fill="white">TikTok</text>
              <text x="20" y="28" text-anchor="middle" font-size="7" font-family="Arial" fill="#69C9D0">Shop</text>
            </svg>',
        default => '
            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
              <rect width="40" height="40" rx="6" fill="#4B5563"/>
              <text x="20" y="24" text-anchor="middle" font-size="14" font-weight="bold" font-family="Arial" fill="white">M</text>
            </svg>',
    };

    // Barcode Code128 como data URI base64
    $barcodeData = $order->order_number . '-V' . $vol;
    $barGen      = new \Picqer\Barcode\BarcodeGeneratorSVG();
    $barcodeSvg  = $barGen->getBarcode($barcodeData, $barGen::TYPE_CODE_128, 2, 55);
    $barcodeUri  = 'data:image/svg+xml;base64,' . base64_encode($barcodeSvg);

    // QR Code se chillerlan/php-qrcode estiver disponível (instalado no Dockerfile)
    $qrUri = null;
    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
        try {
            $qrOptions = new \chillerlan\QRCode\QROptions;
            $qrOptions->outputType   = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
            $qrOptions->scale        = 5;
            $qrOptions->outputBase64 = false;
            $qrPng = (new \chillerlan\QRCode\QRCode($qrOptions))->render($barcodeData);
            $qrUri = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable) {}
    }
@endphp
<div class="page">

    {{-- Volume badge --}}
    <div class="volume-badge">
        <div class="vol-label">Volume</div>
        <div class="vol-number">{{ $vol }}/{{ $total }}</div>
    </div>

    {{-- Marketplace badge --}}
    <div class="mkt-badge" style="background-color: {{ $mktColor }};">
        <div class="mkt-badge-logo">{!! $mktLogoSvg !!}</div>
        <div class="mkt-badge-name" style="color: {{ $mktTextClr }};">{{ $mktLabel }}</div>
        @if($mktAccount && $mktAccount !== $mktLabel)
        <div class="mkt-badge-account" style="color: {{ $mktTextClr }};">{{ $mktAccount }}</div>
        @endif
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

    {{-- Info: número do pedido + prazo ──}}
    <div class="info-row">
        <div class="c1">{{ $order->order_number }}</div>
        <div class="c2">
            @if($label['deadline'])
                Despachar até {{ \Carbon\Carbon::parse($label['deadline'])->format('d/m/Y') }}
            @endif
        </div>
    </div>

    {{-- Barcode Code128 --}}
    <div class="barcode-row">
        <img src="{{ $barcodeUri }}" alt="{{ $barcodeData }}">
        <div class="bc-label">{{ $barcodeData }}</div>
    </div>

    {{-- QR Code (somente se biblioteca disponível) --}}
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
