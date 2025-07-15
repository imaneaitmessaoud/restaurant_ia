<?php

namespace App\Repository;

use App\Entity\MenuCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuCategory>
 */
class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }

    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCategoriesWithItems(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.menuItems', 'mi')
            ->andWhere('c.actif = :actif')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('actif', true)
            ->setParameter('disponible', true)
            ->groupBy('c.id')
            ->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}