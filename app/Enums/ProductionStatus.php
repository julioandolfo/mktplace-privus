<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case NotRequired = 'not_required';
    case Pending     = 'pending';
    case InProgress  = 'in_progress';
    case Complete    = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Não requerida',
            self::Pending     => 'Aguardando produção',
            self::InProgress  => 'Em produção',
            self::Complete    => 'Concluída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotRequired => 'secondary',
            self::Pending     => 'warning',
            self::InProgress  => 'info',
            self::Complete    => 'success',
        };
    }
}
