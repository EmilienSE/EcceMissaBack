<?php

namespace App\Repository;

use App\Entity\Eglise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Eglise>
 *
 * @method Eglise|null find($id, $lockMode = null, $lockVersion = null)
 * @method Eglise|null findOneBy(array $criteria, array $orderBy = null)
 * @method Eglise[]    findAll()
 * @method Eglise[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EgliseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Eglise::class);
    }

    //    /**
    //     * @return Eglise[] Returns an array of Eglise objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Eglise
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
