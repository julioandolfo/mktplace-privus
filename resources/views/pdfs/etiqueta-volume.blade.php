<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: 100mm 150mm;
        margin: 0mm;
    }
    html, body {
        margin: 0;
        padding: 0;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 8pt;
    }

    .etiqueta {
        width: 92mm;
        padding: 4mm;
        page-break-inside: avoid;
    }

    /* ── Volume ── */
    .vol { background: #1a1a2e; color: #fff; text-align: center; padding: 3px 0; }
    .vol small { font-size: 6pt; letter-spacing: 2px; text-transform: uppercase; display: block; }
    .vol b { font-size: 18pt; letter-spacing: 2px; }

    /* ── Marketplace ── */
    .mkt { padding: 3px 4px; }
    .mkt-tbl { width: 100%; }
    .mkt-tbl td { vertical-align: middle; }
    .mkt-cod { width: 22px; text-align: center; font-size: 7pt; font-weight: bold; }
    .mkt-cod span { padding: 1px 3px; border-radius: 2px; }
    .mkt-lbl { font-size: 9pt; font-weight: bold; padding-left: 4px; }
    .mkt-acc { text-align: right; font-size: 6pt; }

    /* ── Seções ── */
    .stitle { font-size: 6pt; color: #888; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #ddd; padding-bottom: 1px; margin-bottom: 2px; }
    .sbody { font-size: 8pt; line-height: 1.35; }
    .sbody b { font-size: 9pt; }
    .sep { border-top: 2px solid #1a1a2e; margin: 2mm 0; }
    .sec { margin-bottom: 2mm; }

    /* ── Info ── */
    .inf { width: 100%; border-top: 1px dashed #ccc; margin-top: 2mm; }
    .inf td { font-size: 6pt; color: #666; padding-top: 1mm; }
    .inf .r { text-align: right; }

    /* ── Barcode / QR ── */
    .bc { text-align: center; margin-top: 1.5mm; padding-top: 1.5mm; border-top: 1px solid #eee; }
    .bc img { width: 80mm; height: 8mm; }
    .bc-t { font-family: monospace; font-size: 6pt; color: #444; margin-top: 1px; }
    .qr { text-align: center; margin-top: 1.5mm; }
    .qr img { width: 18mm; height: 18mm; }
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

    $mktColor   = $mkt?->color()  ?? '#4B5563';
    $mktLabel   = $mkt?->label()  ?? ($label['account_name'] ?: 'Marketplace');
    $mktAccount = $label['account_name'] ?? '';
    $isDark     = in_array($mkt?->value, ['mercado_livre', 'amazon']);
    $txtClr     = $isDark ? '#1a1a2e' : '#ffffff';
    $codBg      = $isDark ? '#00000022' : '#ffffff33';
    $mktCode    = match($mkt?->value) {
        'mercado_livre' => 'ML',
        'shopee'        => 'SP',
        'amazon'        => 'AMZ',
        'woocommerce'   => 'WC',
        'tiktok'        => 'TT',
        default         => 'MKT',
    };

    $bcData  = $order->order_number . '-V' . $vol;
    $barGen  = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $bcPng   = $barGen->getBarcode($bcData, $barGen::TYPE_CODE_128, 2, 40);
    $bcUri   = 'data:image/png;base64,' . base64_encode($bcPng);

    $qrUri = null;
    if (class_exists(\chillerlan\QRCode\QRCode::class)) {
        try {
            $opts = new \chillerlan\QRCode\QROptions;
            $opts->outputType   = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
            $opts->scale        = 4;
            $opts->outputBase64 = false;
            $qrPng = (new \chillerlan\QRCode\QRCode($opts))->render($bcData);
            $qrUri = 'data:image/png;base64,' . base64_encode($qrPng);
        } catch (\Throwable) {}
    }
@endphp
@if(!$loop->first)<div style="page-break-before:always;"></div>@endif
<div class="etiqueta">

    {{-- Volume --}}
    <div class="vol">
        <small>Volume</small>
        <b>{{ $vol }}/{{ $total }}</b>
    </div>

    {{-- Marketplace --}}
    <div class="mkt" style="background:{{ $mktColor }};">
        <table class="mkt-tbl" cellspacing="0" cellpadding="0">
            <tr>
                <td class="mkt-cod"><span style="background:{{ $codBg }};color:{{ $txtClr }};">{{ $mktCode }}</span></td>
                <td class="mkt-lbl" style="color:{{ $txtClr }};">{{ $mktLabel }}</td>
                @if($mktAccount && $mktAccount !== $mktLabel)
                <td class="mkt-acc" style="color:{{ $txtClr }};">{{ $mktAccount }}</td>
                @endif
            </tr>
        </table>
    </div>

    {{-- Remetente --}}
    <div class="sec">
        <div class="stitle">Remetente</div>
        <div class="sbody">
            <b>{{ $company->trade_name ?? $company->name }}</b><br>
            @if($company->document)
            {{ $company->document_type === 'cnpj' ? 'CNPJ' : 'CPF' }}: {{ $company->formatted_document }}<br>
            @endif
            @php $ca = $company->address ?? []; @endphp
            {{ $ca['street'] ?? '' }}@if(!empty($ca['number'])), {{ $ca['number'] }}@endif<br>
            {{ $ca['city'] ?? '' }}{{ !empty($ca['state']) ? ' - '.$ca['state'] : '' }}
        </div>
    </div>

    <div class="sep"></div>

    {{-- Destinatário --}}
    <div class="sec">
        <div class="stitle">Destinatário</div>
        <div class="sbody">
            <b>{{ $order->customer_name }}</b><br>
            @if(!empty($addr['street']))
            {{ $addr['street'] }}@if(!empty($addr['number'])), {{ $addr['number'] }}@endif<br>
            @endif
            @if(!empty($addr['complement'])){{ $addr['complement'] }}<br>@endif
            @if(!empty($addr['neighborhood'])){{ $addr['neighborhood'] }}<br>@endif
            {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? ' - '.$addr['state'] : '' }}<br>
            @if(!empty($addr['zip']))CEP {{ $addr['zip'] }}@endif
            @if(!empty($addr['zipcode']))CEP {{ $addr['zipcode'] }}@endif
        </div>
    </div>

    {{-- Info --}}
    <table class="inf" cellspacing="0" cellpadding="0">
        <tr>
            <td>{{ $order->order_number }}</td>
            <td class="r">@if($label['deadline'])Despachar até {{ \Carbon\Carbon::parse($label['deadline'])->format('d/m/Y') }}@endif</td>
        </tr>
    </table>

    {{-- Barcode --}}
    <div class="bc">
        <img src="{{ $bcUri }}">
        <div class="bc-t">{{ $bcData }}</div>
    </div>

    {{-- QR Code --}}
    @if($qrUri)
    <div class="qr">
        <img src="{{ $qrUri }}">
    </div>
    @endif

</div>
@endforeach
</body>
</html>
