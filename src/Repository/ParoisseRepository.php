<?php

namespace App\Repository;

use App\Entity\Paroisse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paroisse>
 *
 * @method Paroisse|null find($id, $lockMode = null, $lockVersion = null)
 * @method Paroisse|null findOneBy(array $criteria, array $orderBy = null)
 * @method Paroisse[]    findAll()
 * @method Paroisse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParoisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paroisse::class);
    }

//    /**
//     * @return Paroisse[] Returns an array of Paroisse objects
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

//    public function findOneBySomeField($value): ?Paroisse
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
