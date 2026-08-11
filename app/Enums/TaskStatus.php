<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pendente = 'pendente';
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';

    /**
     * Portuguese label for display — kept separate from the enum value so
     * the API/DB value stays a stable, URL-safe token (no spaces/accents)
     * independent of how it's worded on screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAndamento => 'Em andamento',
            self::Concluida => 'Concluída',
        };
    }
}
