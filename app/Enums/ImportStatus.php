<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Previewing = 'previewing';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
