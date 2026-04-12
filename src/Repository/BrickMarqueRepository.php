<?php

namespace App\Repository;

use App\Entity\BrickMarque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BrickMarqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BrickMarque::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
