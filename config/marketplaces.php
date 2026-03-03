<?php

// Credenciais (client_id, client_secret, etc.) são configuradas pelo painel em
// Configurações > Marketplaces e armazenadas via SystemSetting (grupo "marketplaces").
// Este arquivo contém apenas os endpoints estáticos de cada marketplace.

return [

    'mercado_livre' => [
        'auth_url'  => 'https://auth.mercadolivre.com.br/authorization',
        'token_url' => 'https://api.mercadolibre.com/oauth/token',
        'api_url'   => 'https://api.mercadolibre.com',
    ],

    'amazon' => [
        'auth_url'  => 'https://sellercentral.amazon.com.br/apps/authorize/consent',
        'token_url' => 'https://api.amazon.com/auth/o2/token',
        'api_url'   => 'https://sellingpartnerapi-na.amazon.com',
    ],

    // Shopee usa assinatura HMAC — Partner ID e Key configurados nas Configurações
    'shopee' => [
        'api_url' => 'https://partner.shopeemobile.com',
    ],

    // WooCommerce usa REST API Key gerado no painel do cliente — configurado por conta
    'woocommerce' => [
        'api_url' => null,
    ],

    // TikTok Shop — credenciais configuradas nas Configurações
    'tiktok' => [
        'api_url' => 'https://open-api.tiktokglobalshop.com',
    ],

];
