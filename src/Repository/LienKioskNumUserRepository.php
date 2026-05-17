<?php

namespace App\Repository;

use App\Entity\KioskNum;
use App\Entity\LienKioskNumUser;
use App\Entity\User;
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

    /**
     * @return LienKioskNumUser[]
     */
    public function findByUserWithNumero(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.kioskNum', 'n')->addSelect('n')
            ->innerJoin('n.kioskCollec', 'kc')->addSelect('kc')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('kc.nom', 'ASC')
            ->addOrderBy('n.num', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return LienKioskNumUser[]
     */
    public function findByNumero(KioskNum $numero): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.user', 'u')->addSelect('u')
            ->where('l.kioskNum = :numero')
            ->setParameter('numero', $numero)
            ->orderBy('u.name', 'ASC')
            ->addOrderBy('u.lastname', 'ASC')
            ->getQuery()
            ->getResult();
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
