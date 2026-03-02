<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Kit = 'kit';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simples',
            self::Variable => 'Com Variantes',
            self::Kit => 'Kit',
        };
    }
}
