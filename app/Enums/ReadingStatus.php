<?php

namespace App\Enums;

enum ReadingStatus: string
{
    case Reading = 'reading';
    case Read = 'read';
    case Want = 'want';

    public function label(): string
    {
        return match ($this) {
            self::Reading => 'Чета',
            self::Read => 'Прочетени',
            self::Want => 'За четене',
        };
    }
}
