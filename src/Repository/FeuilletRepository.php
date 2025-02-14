<?php

namespace App\Repository;

use App\Entity\Feuillet;
use App\Entity\Paroisse;
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

    // FeuilletRepository.php

    public function findOneByNearestCelebrationDate(Paroisse $paroisse, \DateTime $currentDate)
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT * FROM feuillet 
            WHERE paroisse_id = :paroisse 
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, celebration_date, :currentDate)) ASC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);  // Prépare la requête
        $result = $stmt->executeQuery(['paroisse' => $paroisse->getId(), 'currentDate' => $currentDate->format('Y-m-d H:i')]);
    
        $row = $result->fetchAssociative(); // ✅ Fonctionne avec Result en Doctrine 3
    
        return $row ? $this->getEntityManager()->getRepository(Feuillet::class)->find($row['id']) : null;
    }
    

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
