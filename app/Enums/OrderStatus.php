<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Produced = 'produced';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Confirmed => 'Confirmado',
            self::InProduction => 'Em Producao',
            self::Produced => 'Produzido',
            self::ReadyToShip => 'Pronto p/ Envio',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
            self::Returned => 'Devolvido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::InProduction => 'info',
            self::Produced => 'info',
            self::ReadyToShip => 'success',
            self::Shipped => 'success',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::Returned => 'danger',
        };
    }
}
