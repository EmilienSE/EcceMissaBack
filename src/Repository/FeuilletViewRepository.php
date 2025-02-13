<?php

namespace App\Repository;

use App\Entity\FeuilletView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeuilletView>
 *
 * @method FeuilletView|null find($id, $lockMode = null, $lockVersion = null)
 * @method FeuilletView|null findOneBy(array $criteria, array $orderBy = null)
 * @method FeuilletView[]    findAll()
 * @method FeuilletView[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FeuilletViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeuilletView::class);
    }

    //    /**
    //     * @return FeuilletView[] Returns an array of FeuilletView objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?FeuilletView
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
