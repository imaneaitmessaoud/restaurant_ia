<?php

namespace App\Repository;

use App\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    public function findAvailableItems(): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('disponible', true)
            ->orderBy('mi.ordre', 'ASC')
            ->addOrderBy('mi.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.category = :categoryId')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('disponible', true)
            ->orderBy('mi.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPopularItems(int $limit = 10): array
    {
        return $this->createQueryBuilder('mi')
            ->leftJoin('mi.commandeItems', 'ci')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('disponible', true)
            ->groupBy('mi.id')
            ->orderBy('COUNT(ci.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.prix BETWEEN :minPrice AND :maxPrice')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->setParameter('disponible', true)
            ->orderBy('mi.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByIngredient(string $ingredient): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('JSON_CONTAINS(mi.ingredients, :ingredient) = 1')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('ingredient', json_encode($ingredient))
            ->setParameter('disponible', true)
            ->getQuery()
            ->getResult();
    }

    public function findWithoutAllergene(string $allergene): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('JSON_CONTAINS(mi.allergenes, :allergene) = 0 OR mi.allergenes IS NULL')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('allergene', json_encode($allergene))
            ->setParameter('disponible', true)
            ->getQuery()
            ->getResult();
    }

    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('mi')
            ->andWhere('mi.nom LIKE :searchTerm OR mi.description LIKE :searchTerm')
            ->andWhere('mi.disponible = :disponible')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('disponible', true)
            ->orderBy('mi.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}