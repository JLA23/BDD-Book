<?php

namespace App\Repository;

use App\Entity\LienKioskNumUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LienKioskNumUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method LienKioskNumUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method LienKioskNumUser[]    findAll()
 * @method LienKioskNumUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LienKioskNumUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LienKioskNumUser::class);
    }

    // /**
    //  * @return LienKioskNumUser[] Returns an array of LienKioskNumUser objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LienKioskNumUser
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
