<?php

namespace App\Enum;

enum TypeServiceEnum: string
{
    case SUR_PLACE = 'sur_place';
    case EMPORTER = 'emporter';
    case LIVRAISON = 'livraison';
    
    public function getLabel(): string
    {
        return match($this) {
            self::SUR_PLACE => 'Sur place',
            self::EMPORTER => 'À emporter',
            self::LIVRAISON => 'Livraison',
        };
    }
    
    public function needsAddress(): bool
    {
        return $this === self::LIVRAISON;
    }
    
    public function hasDeliveryFee(): bool
    {
        return $this === self::LIVRAISON;
    }
    
    public function getEstimatedTime(): int
    {
        return match($this) {
            self::SUR_PLACE => 20,      // 20 minutes
            self::EMPORTER => 15,       // 15 minutes
            self::LIVRAISON => 35,      // 35 minutes
        };
    }
}