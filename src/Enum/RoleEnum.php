<?php

namespace App\Enum;

enum RoleEnum: string
{
    case CLIENT = 'client';
    case ADMIN = 'admin';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CLIENT => 'Client',
            self::ADMIN => 'Administrateur',
        };
    }
}