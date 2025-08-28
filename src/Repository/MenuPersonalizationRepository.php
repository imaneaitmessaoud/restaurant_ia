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
    public function findActiveByItemName(string $itemName): array
{
    return $this->createQueryBuilder('p')
        ->innerJoin('p.menuItem', 'mi')
        ->andWhere('LOWER(mi.nom) = LOWER(:n)')
        ->andWhere('p.actif = :a')
        ->setParameter('n', $itemName)
        ->setParameter('a', true)
        ->orderBy('p.ordre', 'ASC')
        ->getQuery()
        ->getResult();
}
public function calculatePrice(mixed $choice): float
{
    $base   = $this->getPrixSupplementFloat(); // prix “de base” s’il n’y a pas de delta par valeur
    $values = $this->getOptionsJson();         // attendu: key => ['label'=>..., 'delta'=>float]

    // CASE 1: checkbox (plusieurs valeurs)
    if ($this->isMultipleChoice()) {
        $sum = 0.0;
        if (is_array($choice)) {
            foreach ($choice as $val) {
                $sum += isset($values[$val]['delta']) ? (float)$values[$val]['delta'] : $base;
            }
        } elseif ($choice === true) {
            $sum += $base;
        }
        return $sum;
    }

    // CASE 2: radio/select (une valeur)
    if ($this->isSingleChoice()) {
        if (is_string($choice) && isset($values[$choice]['delta'])) {
            return (float)$values[$choice]['delta'];
        }
        return is_string($choice) ? $base : 0.0;
    }

    // CASE 3: number (quantité) -> ex: “extra fromage x2”
    if (is_numeric($choice)) {
        return $base * (int)$choice;
    }

    // CASE 4: booléen
    if ($choice === true) {
        return $base;
    }

    return 0.0;
}

}