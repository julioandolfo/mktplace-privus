<?php

return [

    'mercado_livre' => [
        'client_id'     => env('MERCADO_LIVRE_CLIENT_ID'),
        'client_secret' => env('MERCADO_LIVRE_CLIENT_SECRET'),
        'auth_url'      => 'https://auth.mercadolivre.com.br/authorization',
        'token_url'     => 'https://api.mercadolibre.com/oauth/token',
        'api_url'       => 'https://api.mercadolibre.com',
        'scopes'        => [],
    ],

    'amazon' => [
        'client_id'     => env('AMAZON_CLIENT_ID'),
        'client_secret' => env('AMAZON_CLIENT_SECRET'),
        'auth_url'      => 'https://sellercentral.amazon.com.br/apps/authorize/consent',
        'token_url'     => 'https://api.amazon.com/auth/o2/token',
        'api_url'       => 'https://sellingpartnerapi-na.amazon.com',
        'scopes'        => [],
    ],

    // Shopee usa assinatura HMAC — integração manual via API Key
    'shopee' => [
        'partner_id'  => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'api_url'     => 'https://partner.shopeemobile.com',
    ],

    // WooCommerce usa REST API Key gerado no painel do cliente
    'woocommerce' => [
        'api_url' => null,
    ],

    // TikTok Shop — app review necessária
    'tiktok' => [
        'app_id'     => env('TIKTOK_APP_ID'),
        'app_secret' => env('TIKTOK_APP_SECRET'),
        'api_url'    => 'https://open-api.tiktokglobalshop.com',
    ],

];
