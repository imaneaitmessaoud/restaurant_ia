<?php

namespace App\Enum;

enum StatutUserEnum: string
{
    case ACTIF = 'actif';
    case INACTIF = 'inactif';
    case SUSPENDU = 'suspendu';
    
    public function getLabel(): string
    {
        return match($this) {
            self::ACTIF => 'Actif',
            self::INACTIF => 'Inactif',
            self::SUSPENDU => 'Suspendu',
        };
    }
    
    public function isActive(): bool
    {
        return $this === self::ACTIF;
    }
    
    public function canLogin(): bool
    {
        return $this === self::ACTIF;
    }
}