<?php

namespace App\DataFixtures;

use App\Entity\MenuCategory;
use App\Entity\MenuItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MenuItemsFixture extends Fixture
{
    public const REF_MARGHERITA = 'menuitem-margherita';
    public const REF_COCA       = 'menuitem-coca';

    public function load(ObjectManager $em): void
    {
        // Catégorie simple "Pizzas"
        $catPizza = (new MenuCategory())
            ->setNom('Pizzas')
            ->setDescription('Nos pizzas maison');
        $em->persist($catPizza);

        // Catégorie "Boissons"
        $catDrink = (new MenuCategory())
            ->setNom('Boissons')
            ->setDescription('Boissons fraîches');
        $em->persist($catDrink);

        // Margherita
        $marg = (new MenuItem())
            ->setNom('Margherita')
            ->setDescription('Tomate, mozzarella, basilic')
            ->setPrix('45.00')
            ->setCategory($catPizza);
        $em->persist($marg);
        $this->addReference(self::REF_MARGHERITA, $marg);

        // Coca 33cl
        $coca = (new MenuItem())
            ->setNom('Coca 33cl')
            ->setDescription('Boisson gazeuse 33cl')
            ->setPrix('12.00')
            ->setCategory($catDrink);
        $em->persist($coca);
        $this->addReference(self::REF_COCA, $coca);

        $em->flush();
    }
}
