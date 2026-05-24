<?php

namespace App\Repository;

use App\Entity\GameStore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameStore>
 */
class GameStoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameStore::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.actif = true')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
