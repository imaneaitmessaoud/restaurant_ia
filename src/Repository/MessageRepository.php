<?php

namespace App\Repository;

use App\Entity\Message;
use App\Enum\SenderTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findUnreadMessages(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.readAt IS NULL')
            ->orderBy('m.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySenderType(SenderTypeEnum $senderType): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.senderType = :type')
            ->setParameter('type', $senderType)
            ->orderBy('m.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findMessagesContaining(string $keyword): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.contenu LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('m.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByConversationId(int $conversationId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :convId')
            ->setParameter('convId', $conversationId)
            ->orderBy('m.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }
}