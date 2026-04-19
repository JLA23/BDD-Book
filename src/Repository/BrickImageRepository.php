<?php

namespace App\Repository;

use App\Entity\BrickImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BrickImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BrickImage::class);
    }

    public function findBySetOrdered(int $setId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.brickSet = :setId')
            ->setParameter('setId', $setId)
            ->orderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxPosition(int $setId): int
    {
        $result = $this->createQueryBuilder('i')
            ->select('MAX(i.position)')
            ->where('i.brickSet = :setId')
            ->setParameter('setId', $setId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }
}
