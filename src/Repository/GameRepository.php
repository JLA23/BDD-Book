<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearch(?string $search = null, ?string $console = null, ?string $genre = null): array
    {
        $qb = $this->createQueryBuilder('g');

        if ($search) {
            $qb->andWhere('g.titre LIKE :search OR g.editeur LIKE :search OR g.developpeur LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($console) {
            $qb->andWhere('g.console = :console')
               ->setParameter('console', $console);
        }

        if ($genre) {
            $qb->andWhere('g.genre LIKE :genre')
               ->setParameter('genre', '%' . $genre . '%');
        }

        return $qb->orderBy('g.titre', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    public function findByExternalId(string $externalId): ?Game
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function findByTitreAndConsole(string $titre, string $console): ?Game
    {
        return $this->createQueryBuilder('g')
            ->where('g.titre = :titre')
            ->andWhere('g.console = :console')
            ->setParameter('titre', $titre)
            ->setParameter('console', $console)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getDistinctConsoles(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('DISTINCT g.console')
            ->where('g.console IS NOT NULL')
            ->orderBy('g.console', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'console');
    }

    public function getDistinctGenres(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('DISTINCT g.genre')
            ->where('g.genre IS NOT NULL')
            ->orderBy('g.genre', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'genre');
    }

    public function countAll(): int
    {
        return $this->count([]);
    }
}
