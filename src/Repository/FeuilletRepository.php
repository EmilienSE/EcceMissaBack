<?php

namespace App\Repository;

use App\Entity\Feuillet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feuillet>
 *
 * @method Feuillet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Feuillet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Feuillet[]    findAll()
 * @method Feuillet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FeuilletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feuillet::class);
    }

    //    /**
    //     * @return Feuillet[] Returns an array of Feuillet objects
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

    //    public function findOneBySomeField($value): ?Feuillet
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
