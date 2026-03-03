<?php

namespace App\Enums;

enum MarketplaceType: string
{
    case MercadoLivre = 'mercado_livre';
    case Shopee = 'shopee';
    case WooCommerce = 'woocommerce';
    case Amazon = 'amazon';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::MercadoLivre => 'Mercado Livre',
            self::Shopee => 'Shopee',
            self::WooCommerce => 'WooCommerce',
            self::Amazon => 'Amazon',
            self::TikTok => 'TikTok Shop',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MercadoLivre => '#FFE600',
            self::Shopee => '#EE4D2D',
            self::WooCommerce => '#96588A',
            self::Amazon => '#FF9900',
            self::TikTok => '#000000',
        };
    }

    public function supportsOAuth(): bool
    {
        return match ($this) {
            self::MercadoLivre, self::Amazon => true,
            self::Shopee, self::WooCommerce, self::TikTok => false,
        };
    }

    public function oauthConfigKey(): string
    {
        return $this->value;
    }
}
