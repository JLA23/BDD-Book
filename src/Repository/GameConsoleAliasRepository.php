<?php

namespace App\Repository;

use App\Entity\GameConsoleAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameConsoleAlias>
 */
class GameConsoleAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameConsoleAlias::class);
    }

    public function findOneByLibelleInsensitive(string $libelle): ?GameConsoleAlias
    {
        $libelle = trim($libelle);
        if ($libelle === '') {
            return null;
        }

        return $this->createQueryBuilder('a')
            ->where('LOWER(TRIM(a.libelle)) = LOWER(:libelle)')
            ->setParameter('libelle', $libelle)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
