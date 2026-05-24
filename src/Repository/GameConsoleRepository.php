<?php

namespace App\Repository;

use App\Entity\GameConsole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameConsole>
 */
class GameConsoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameConsole::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.actif = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCode(string $code): ?GameConsole
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Consoles utilisées comme filtres IGDB (id renseigné, actives).
     *
     * @return GameConsole[]
     */
    public function findWithIgdbPlatformForApiFilter(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.actif = true')
            ->andWhere('c.igdbPlatformId IS NOT NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
