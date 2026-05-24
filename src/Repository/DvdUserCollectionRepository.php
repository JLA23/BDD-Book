<?php

namespace App\Repository;

use App\Entity\DvdUserCollection;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DvdUserCollection>
 */
class DvdUserCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DvdUserCollection::class);
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('duc')
            ->select('COUNT(duc.id)')
            ->where('duc.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('duc')
            ->leftJoin('duc.dvd', 'd')
            ->addSelect('d')
            ->where('duc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDvd(\App\Entity\Dvd $dvd): array
    {
        return $this->createQueryBuilder('duc')
            ->leftJoin('duc.user', 'u')
            ->addSelect('u')
            ->where('duc.dvd = :dvd')
            ->setParameter('dvd', $dvd)
            ->getQuery()
            ->getResult();
    }

    public function countByDvd(\App\Entity\Dvd $dvd): int
    {
        return (int) $this->createQueryBuilder('duc')
            ->select('COUNT(duc.id)')
            ->where('duc.dvd = :dvd')
            ->setParameter('dvd', $dvd)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
