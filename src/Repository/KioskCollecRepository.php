<?php

namespace App\Repository;

use App\Entity\KIOSKCOLLEC;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method KioskCollec|null find($id, $lockMode = null, $lockVersion = null)
 * @method KioskCollec|null findOneBy(array $criteria, array $orderBy = null)
 * @method KioskCollec[]    findAll()
 * @method KioskCollec[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KioskCollecRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KIOSKCOLLEC::class);
    }

    // /**
    //  * @return KIOSKCOLLEC[] Returns an array of KIOSKCOLLEC objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('k.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?KIOSKCOLLEC
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
