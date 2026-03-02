<?php

namespace App\Enums;

enum NfeStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Contingency = 'contingency';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Processing => 'Processando',
            self::Approved => 'Aprovada',
            self::Rejected => 'Rejeitada',
            self::Cancelled => 'Cancelada',
            self::Contingency => 'Contingencia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
            self::Contingency => 'warning',
        };
    }
}
