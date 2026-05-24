<?php

namespace App\Repository;

use App\Entity\GameTypeEdition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameTypeEdition>
 */
class GameTypeEditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameTypeEdition::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCode(string $code): ?GameTypeEdition
    {
        return $this->findOneBy(['code' => $code]);
    }
}
