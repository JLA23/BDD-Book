<?php

namespace App\Repository;

use App\Entity\KioskNum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method KioskNum|null find($id, $lockMode = null, $lockVersion = null)
 * @method KioskNum|null findOneBy(array $criteria, array $orderBy = null)
 * @method KioskNum[]    findAll()
 * @method KioskNum[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KioskNumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KioskNum::class);
    }

    // /**
    //  * @return KioskNum[] Returns an array of KioskNum objects
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
    public function findOneBySomeField($value): ?KioskNum
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
