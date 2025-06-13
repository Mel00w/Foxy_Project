<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findUnreadMessagesByUser($user)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.isRead = :isRead')
            ->andWhere('m.sender != :user')
            ->andWhere('m.conversation IN (
                SELECT c.id FROM App\Entity\Conversation c 
                WHERE c.parent = :user OR :user MEMBER OF c.teamMembers
            )')
            ->setParameter('isRead', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findNewerMessages($conversation, ?\DateTimeImmutable $lastMessageTime = null)
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.createdAt', 'ASC');

        if ($lastMessageTime) {
            $qb->andWhere('m.createdAt > :lastMessageTime')
               ->setParameter('lastMessageTime', $lastMessageTime);
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnreadMessages(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.conversation', 'c')
            ->where('c.parent = :user OR :user MEMBER OF c.teamMembers')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
} 