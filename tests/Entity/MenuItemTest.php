<?php

namespace App\Tests\Entity;

use App\Entity\MenuItem;
use App\Entity\MenuCategory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class MenuItemTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreateMenuItem(): void
    {
        // Créer une catégorie
        $category = new MenuCategory();
        $category->setNom('Test Catégorie');

        // Créer un MenuItem
        $menuItem = new MenuItem();
        $menuItem->setNom('Pizza test')
                 ->setDescription('Pizza au fromage testée')
                 ->setPrixFloat(79.90)
                 ->setDisponible(true)
                 ->setImage('pizza.jpg')
                 ->setCategory($category)
                 ->setTempsPreparation(20)
                 ->setIngredients(['fromage', 'tomate'])
                 ->setAllergenes(['lait']);

        // Persist + flush
        $this->entityManager->persist($category);
        $this->entityManager->persist($menuItem);
        $this->entityManager->flush();

        // Vérification que l'ID a bien été généré
        $this->assertNotNull($menuItem->getId());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
