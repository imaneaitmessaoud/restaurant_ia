<?php

namespace App\Repository;

use App\Entity\MenuPersonalization;
use App\Enum\PersonalizationTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuPersonalization>
 */
class MenuPersonalizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuPersonalization::class);
    }

    public function findByMenuItem(int $menuItemId): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.menuItem = :menuItemId')
            ->andWhere('mp.actif = :actif')
            ->setParameter('menuItemId', $menuItemId)
            ->setParameter('actif', true)
            ->orderBy('mp.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findObligatoryByMenuItem(int $menuItemId): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.menuItem = :menuItemId')
            ->andWhere('mp.obligatoire = :obligatoire')
            ->andWhere('mp.actif = :actif')
            ->setParameter('menuItemId', $menuItemId)
            ->setParameter('obligatoire', true)
            ->setParameter('actif', true)
            ->orderBy('mp.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(PersonalizationTypeEnum $type): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.type = :type')
            ->andWhere('mp.actif = :actif')
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('mp.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithPriceSupplement(): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.prixSupplement IS NOT NULL')
            ->andWhere('mp.prixSupplement > 0')
            ->andWhere('mp.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('mp.prixSupplement', 'DESC')
            ->getQuery()
            ->getResult();
    }
}