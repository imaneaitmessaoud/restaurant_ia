<?php

namespace App\Enum;

enum PersonalizationTypeEnum: string
{
    case TAILLE = 'taille';
    case SUCRE = 'sucre';
    case GLACE = 'glace';
    case PATE = 'pate';
    case FROMAGE = 'fromage';
    case CUISSON = 'cuisson';
    case SUPPLEMENT = 'supplement'; // <-- AJOUT

    public function getLabel(): string
    {
        return match($this) {
            self::TAILLE => 'Taille',
            self::SUCRE => 'Sucre',
            self::GLACE => 'Glace',
            self::PATE => 'Pâte',
            self::FROMAGE => 'Fromage',
            self::CUISSON => 'Cuisson',
            self::SUPPLEMENT=> 'Supplément', // <-- AJOUT

        };
    }
}
