<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; }

        /*
         * Papel: 100mm × 150mm (283pt × 425pt) sem margens.
         * Cada .page ocupa exatamente 100mm × 150mm.
         */
        .page {
            width: 100mm;
            height: 149mm; /* 1mm de margem para não criar página extra */
            padding: 4mm;
            overflow: hidden;
        }

        /* ── Volume badge ── */
        .vol-badge {
            background: #1a1a2e;
            color: white;
            text-align: center;
            padding: 2mm 0;
            border-radius: 3px 3px 0 0;
        }
        .vol-badge-lbl { font-size: 6pt; letter-spacing: 3px; text-transform: uppercase; opacity: 0.75; }
        .vol-badge-num { font-size: 20pt; font-weight: bold; line-height: 1.1; letter-spacing: 2px; }

        /* ── Marketplace badge (sem SVG, só tabela) ── */
        .mkt-row { width: 100%; border-radius: 0 0 3px 3px; margin-bottom: 3mm; }
        .mkt-code {
            width: 20pt;
            padding: 2px 4px;
            text-align: center;
            font-size: 7pt;
            font-weight: bold;
            vertical-align: middle;
        }
        .mkt-code-inner {
            border-radius: 2px;
            padding: 2px 3px;
            display: inline-block;
        }
        .mkt-name {
            padding: 2px 4px;
            font-size: 10pt;
            font-weight: bold;
            vertical-align: middle;
        }
        .mkt-acct {
            padding: 2px 6px;
            font-size: 7pt;
            text-align: right;
            vertical-align: middle;
        }

        /* ── Seções ── */
        .sec { margin-bottom: 3mm; }
        .sec-title {
            font-size: 6.5pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #eee;
            padding-bottom: 1px;
            margin-bottom: 2px;
        }
        .sec-body { font-size: 8.5pt; line-height: 1.4; }
        .sec-body b  { font-size: 9.5pt; }

        /* ── Divider ── */
        .div2 { border-top: 2px solid #1a1a2e; margin: 2mm 0; }

        /* ── Info row ── */
        .info-tbl { width: 100%; border-top: 1px dashed #ccc; margin-top: 2mm; padding-top: 1.5mm; }
        .info-tbl td { font-size: 6.5pt; color: #555; vertical-align: top; }
        .info-tbl .r { text-align: right; }

        /* ── Barcode ── */
        .bc-wrap { text-align: center; margin-top: 2mm; border-top: 1px solid #eee; padding-top: 2mm; }
        .bc-wrap img { display: block; margin: 0 auto; width: 82mm; height: 10mm; }
        .bc-txt { font-family: monospace; font-size: 6.5pt; color: #444; margin-top: 1mm; text-align: center; }

        /* ── QR Code ── */
        .qr-wrap { text-align: center; margin-top: 2mm; }
        .qr-wrap img { display: block; margin: 0 auto; width: 20mm; height: 20mm; }

        /* ── Page break entre etiquetas ── */
        .pg-break { page-break-after: always; }
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
    $mkt     = $label['marketplace_type'] ?? null;

    // Cores, nome e código do marketplace
    $mktColor   = $mkt?->color()  ?? '#4B5563';
    $mktLabel   = $mkt?->label()  ?? ($label['account_name'] ?: 'Marketplace');
    $mktAccount = $label['account_name'] ?? '';
    $isDark     = in_array($mkt?->value, ['mercado_livre', 'amazon']);
    $mktTextClr = $isDark ? '#1a1a2e' : '#ffffff';
    $mktCodeBg  = $isDark ? 'rgba(0,0,0,0.15)' : 'rgba(255,255,255,0.2)';
    $mktCode    = match($mkt?->value) {
        'mercado_livre' => 'ML',
        'shopee'        => 'SP',
        'amazon'        => 'AMZ',
        'woocommerce'   => 'WC',
        'tiktok'        => 'TT',
        default         => 'MKT',
    };

    // Barcode Code128 → data URI base64
    $bcData  = $order->order_number . '-V' . $vol;
    $barGen  = new \Picqer\Barcode\BarcodeGeneratorSVG();
    $bcSvg   = $barGen->getBarcode($bcData, $barGen::TYPE_CODE_128, 2, 55);
    $bcUri   = 'data:image/svg+xml;base64,' . base64_encode($bcSvg);

    // QR Code (chillerlan/php-qrcode — instalado via composer.lock)
    $qrUri = null;
    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
        try {
            $opts = new \chillerlan\QRCode\QROptions;
            $opts->outputType   = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
            $opts->scale        = 5;
            $opts->outputBase64 = false;
            $qrPng = (new \chillerlan\QRCode\QRCode($opts))->render($bcData);
            $qrUri = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable) {}
    }
@endphp

@if(!$loop->first)<div class="pg-break"></div>@endif

<div class="page">

    {{-- ── Volume badge ─────────────────────────── --}}
    <div class="vol-badge">
        <div class="vol-badge-lbl">Volume</div>
        <div class="vol-badge-num">{{ $vol }}/{{ $total }}</div>
    </div>

    {{-- ── Marketplace badge (tabela, sem SVG) ─── --}}
    <table class="mkt-row" cellspacing="0" cellpadding="0" style="background:{{ $mktColor }};">
        <tr>
            <td class="mkt-code" style="color:{{ $mktTextClr }};">
                <div class="mkt-code-inner" style="background:{{ $mktCodeBg }}; color:{{ $mktTextClr }};">
                    {{ $mktCode }}
                </div>
            </td>
            <td class="mkt-name" style="color:{{ $mktTextClr }};">{{ $mktLabel }}</td>
            @if($mktAccount && $mktAccount !== $mktLabel)
            <td class="mkt-acct" style="color:{{ $mktTextClr }};">{{ $mktAccount }}</td>
            @endif
        </tr>
    </table>

    {{-- ── Remetente ─────────────────────────────── --}}
    <div class="sec">
        <div class="sec-title">Remetente</div>
        <div class="sec-body">
            <b>{{ $company->trade_name ?? $company->name }}</b><br>
            @if($company->document)
                {{ $company->document_type === 'cnpj' ? 'CNPJ' : 'CPF' }}: {{ $company->formatted_document }}<br>
            @endif
            @php $ca = $company->address ?? []; @endphp
            {{ $ca['street'] ?? '' }}@if(!empty($ca['number'])), {{ $ca['number'] }}@endif<br>
            {{ $ca['city'] ?? '' }}{{ !empty($ca['state']) ? ' - ' . $ca['state'] : '' }}
        </div>
    </div>

    <div class="div2"></div>

    {{-- ── Destinatário ─────────────────────────── --}}
    <div class="sec">
        <div class="sec-title">Destinatário</div>
        <div class="sec-body">
            <b>{{ $order->customer_name }}</b><br>
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

    {{-- ── Info: pedido + prazo ─────────────────── --}}
    <table class="info-tbl" cellspacing="0" cellpadding="0">
        <tr>
            <td>{{ $order->order_number }}</td>
            <td class="r">
                @if($label['deadline'])
                    Despachar até {{ \Carbon\Carbon::parse($label['deadline'])->format('d/m/Y') }}
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Barcode Code128 ──────────────────────── --}}
    <div class="bc-wrap">
        <img src="{{ $bcUri }}" alt="{{ $bcData }}">
        <div class="bc-txt">{{ $bcData }}</div>
    </div>

    {{-- ── QR Code ───────────────────────────────── --}}
    @if($qrUri)
    <div class="qr-wrap">
        <img src="{{ $qrUri }}" alt="QR {{ $bcData }}">
    </div>
    @endif

</div>
@endforeach
</body>
</html>
