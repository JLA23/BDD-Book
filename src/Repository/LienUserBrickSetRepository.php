<?php

namespace App\Repository;

use App\Entity\LienUserBrickSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LienUserBrickSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LienUserBrickSet::class);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.brickSet', 's')
            ->addSelect('s')
            ->where('l.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function userOwnsSet(int $userId, int $setId): bool
    {
        $result = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.user = :userId')
            ->andWhere('l.brickSet = :setId')
            ->setParameter('userId', $userId)
            ->setParameter('setId', $setId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    public function findUserLink(int $userId, int $setId): ?LienUserBrickSet
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :userId')
            ->andWhere('l.brickSet = :setId')
            ->setParameter('userId', $userId)
            ->setParameter('setId', $setId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
