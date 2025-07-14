<?php

namespace App\Repository;

use App\Entity\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findActiveConversations(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :status')
            ->setParameter('status', 'active')
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPhoneNumber(string $phoneNumber): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.phoneNumber = :phone')
            ->setParameter('phone', $phoneNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findInactiveConversations(int $minutesThreshold = 30): array
    {
        $threshold = new \DateTimeImmutable("-{$minutesThreshold} minutes");
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :status')
            ->andWhere('c.lastMessageAt < :threshold OR c.lastMessageAt IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}