<?php

namespace App\Repository;

use App\Entity\CommandeItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandeItem>
 */
class CommandeItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeItem::class);
    }

    public function findByCommande(int $commandeId): array
    {
        return $this->createQueryBuilder('ci')
            ->andWhere('ci.commande = :commandeId')
            ->setParameter('commandeId', $commandeId)
            ->orderBy('ci.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMenuItem(int $menuItemId): array
    {
        return $this->createQueryBuilder('ci')
            ->andWhere('ci.menuItem = :menuItemId')
            ->setParameter('menuItemId', $menuItemId)
            ->orderBy('ci.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getPopularMenuItems(int $limit = 10): array
    {
        return $this->createQueryBuilder('ci')
            ->select('IDENTITY(ci.menuItem) as menuItemId, COUNT(ci.id) as orderCount, SUM(ci.quantite) as totalQuantity')
            ->groupBy('ci.menuItem')
            ->orderBy('orderCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}