<?php

namespace App\Repository;

use App\Entity\BrickCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BrickCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BrickCollection::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $search): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.nom LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
