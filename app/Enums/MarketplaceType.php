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

    /**
     * Returns an inline SVG badge representing the marketplace.
     */
    public function logoSvg(string $class = 'w-5 h-5'): string
    {
        return match ($this) {
            self::MercadoLivre => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#FFE600"/><text x="12" y="16" text-anchor="middle" font-size="9" font-weight="bold" font-family="Arial,sans-serif" fill="#333333">ML</text></svg>',
            self::Shopee       => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#EE4D2D"/><text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial,sans-serif" fill="#FFFFFF">SP</text></svg>',
            self::WooCommerce  => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#96588A"/><text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial,sans-serif" fill="#FFFFFF">WC</text></svg>',
            self::Amazon       => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#FF9900"/><text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial,sans-serif" fill="#000000">AMZ</text></svg>',
            self::TikTok       => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="24" rx="4" fill="#000000"/><text x="12" y="16" text-anchor="middle" font-size="8" font-weight="bold" font-family="Arial,sans-serif" fill="#FFFFFF">TT</text></svg>',
        };
    }

    /**
     * Returns the tracking URL for the given tracking code and optional shipment ID.
     * ML shipments use their own tracking system; others typically use Correios.
     */
    public function trackingUrl(?string $trackingCode, ?string $mlShippingId = null): ?string
    {
        if (! $trackingCode && ! $mlShippingId) {
            return null;
        }

        return match ($this) {
            self::MercadoLivre => $mlShippingId
                ? 'https://rastreamento.mercadolivre.com.br/item/' . $mlShippingId
                : ($trackingCode ? 'https://rastreamento.correios.com.br/app/index.php?objeto=' . $trackingCode : null),
            default => $trackingCode
                ? 'https://rastreamento.correios.com.br/app/index.php?objeto=' . $trackingCode
                : null,
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
