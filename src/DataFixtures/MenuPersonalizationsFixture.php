<?php

namespace App\DataFixtures;

use App\Entity\MenuPersonalization;
use App\Entity\MenuItem;
use App\Enum\PersonalizationTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MenuPersonalizationsFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ✅ Récupération directe depuis la BDD
        $margherita = $manager->getRepository(MenuItem::class)->findOneBy(['nom' => 'Margherita']);
        $coca       = $manager->getRepository(MenuItem::class)->findOneBy(['nom' => 'Coca 33cl']);

        if (!$margherita || !$coca) {
            throw new \RuntimeException('Menu items manquants. Lance la fixture MenuItemsFixture ou crée ces items.');
        }

        // --- Exemple personnalisation pour Margherita
        $taille = new MenuPersonalization();
        $taille->setMenuItem($margherita);
        $taille->setType(PersonalizationTypeEnum::TAILLE);
        $taille->setOptionsJson([
            'small' => ['label' => 'Petite', 'delta' => 0],
            'large' => ['label' => 'Grande', 'delta' => 10],
        ]);
        $taille->setObligatoire(true);
        $manager->persist($taille);

        // --- Exemple personnalisation pour Coca
        $glace = new MenuPersonalization();
        $glace->setMenuItem($coca);
        $glace->setType(PersonalizationTypeEnum::GLACE);
        $glace->setOptionsJson([
            'oui' => ['label' => 'Avec glace', 'delta' => 0],
            'non' => ['label' => 'Sans glace', 'delta' => 0],
        ]);
        $manager->persist($glace);

        $manager->flush();
    }
}
