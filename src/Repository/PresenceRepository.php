<?php

namespace App\Repository;

use App\Entity\Presence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Presence>
 */
class PresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presence::class);
    }

    /**
     * Retourne les présences d'un enfant pour une semaine donnée (compatible toutes BDD)
     */
    public function findByChildAndWeek($child, $year, $week)
    {
        // Calcule le début et la fin de la semaine ISO
        $start = new \DateTime();
        $start->setISODate($year, $week);
        $start->setTime(0, 0, 0);

        $end = clone $start;
        $end->modify('+6 days')->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.child = :child')
            ->andWhere('p.date >= :start')
            ->andWhere('p.date <= :end')
            ->setParameter('child', $child)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'ASC');
        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Presence[] Returns an array of Presence objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Presence
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}