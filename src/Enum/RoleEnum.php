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
    
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
    
    public function isClient(): bool
    {
        return $this === self::CLIENT;
    }
}