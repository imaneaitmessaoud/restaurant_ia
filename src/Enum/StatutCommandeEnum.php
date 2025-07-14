<?php

namespace App\Enum;

enum StatutCommandeEnum: string
{
    case CONFIRMEE = 'confirmee';
    case EN_PREPARATION = 'en_preparation';
    case PRETE = 'prete';
    case EN_LIVRAISON = 'en_livraison';
    case LIVREE = 'livree';
    case ANNULEE = 'annulee';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CONFIRMEE => 'Confirmée',
            self::EN_PREPARATION => 'En préparation',
            self::PRETE => 'Prête',
            self::EN_LIVRAISON => 'En livraison',
            self::LIVREE => 'Livrée',
            self::ANNULEE => 'Annulée',
        };
    }
    
    public function canTransitionTo(self $newStatut): bool
    {
        return match($this) {
            self::CONFIRMEE => in_array($newStatut, [self::EN_PREPARATION, self::ANNULEE]),
            self::EN_PREPARATION => in_array($newStatut, [self::PRETE, self::ANNULEE]),
            self::PRETE => in_array($newStatut, [self::EN_LIVRAISON, self::LIVREE]),
            self::EN_LIVRAISON => in_array($newStatut, [self::LIVREE]),
            self::LIVREE => false,
            self::ANNULEE => false,
        };
    }
    
    public function isFinished(): bool
    {
        return in_array($this, [self::LIVREE, self::ANNULEE]);
    }
    
    public function isActive(): bool
    {
        return !$this->isFinished();
    }
}