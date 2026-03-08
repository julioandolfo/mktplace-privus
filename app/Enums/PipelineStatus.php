<?php

namespace App\Enums;

enum PipelineStatus: string
{
    case AwaitingProduction = 'awaiting_production';
    case InProduction       = 'in_production';
    case ReadyToShip        = 'ready_to_ship';
    case Packing            = 'packing';
    case Packed             = 'packed';
    case PartiallyShipped   = 'partially_shipped';
    case Shipped            = 'shipped';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingProduction => 'Aguardando Produção',
            self::InProduction       => 'Em Produção',
            self::ReadyToShip        => 'Pronto para Envio',
            self::Packing            => 'Em Embalagem',
            self::Packed             => 'Embalado',
            self::PartiallyShipped   => 'Parcialmente Enviado',
            self::Shipped            => 'Despachado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingProduction => 'warning',
            self::InProduction       => 'info',
            self::ReadyToShip        => 'success',
            self::Packing            => 'warning',
            self::Packed             => 'primary',
            self::PartiallyShipped   => 'warning',
            self::Shipped            => 'success',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::AwaitingProduction => 'badge-warning',
            self::InProduction       => 'badge-info',
            self::ReadyToShip        => 'badge-success',
            self::Packing            => 'badge-warning',
            self::Packed             => 'badge-primary',
            self::PartiallyShipped   => 'badge-warning',
            self::Shipped            => 'badge-success',
        };
    }

    /** Status que aparecem na expedição (prontos para despacho) */
    public static function expeditionStatuses(): array
    {
        return [
            self::ReadyToShip,
            self::Packing,
            self::Packed,
            self::PartiallyShipped,
        ];
    }

    /** Status que bloqueiam a entrada na expedição (em produção) */
    public static function productionStatuses(): array
    {
        return [
            self::AwaitingProduction,
            self::InProduction,
        ];
    }
}
