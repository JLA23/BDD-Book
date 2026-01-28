<?php

namespace App\Repository;

use App\Entity\KioskCollec;
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
        parent::__construct($registry, KioskCollec::class);
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

    public function searchByName(string $search): array
    {
        return $this->createQueryBuilder('k')
            ->where('LOWER(k.nom) LIKE LOWER(:search)')
            ->orWhere('LOWER(k.editeur) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('k.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
