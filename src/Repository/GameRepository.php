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

    public function findBySearch(?string $search = null, ?string $console = null, ?string $genre = null, ?string $year = null): array
    {
        $qb = $this->createQueryBuilder('g')->distinct(true);
        // Précharger liens + consoles pour les vues liste (badges) sans N+1
        $qb->leftJoin('g.userLinks', 'link')->addSelect('link')
            ->leftJoin('link.consoleEntity', 'gc')->addSelect('gc');

        if ($search) {
            $qb->andWhere('g.titre LIKE :search OR g.editeur LIKE :search OR g.developpeur LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($console) {
            $qb->andWhere('gc.code = :console')
               ->setParameter('console', $console);
        }

        if ($genre) {
            $qb->andWhere('g.genre LIKE :genre')
               ->setParameter('genre', '%' . $genre . '%');
        }

        if ($year) {
            $qb->andWhere('g.annee = :year')
               ->setParameter('year', $year);
        }

        $qb->orderBy('g.titre', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findByExternalId(string $externalId): ?Game
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function findByTitreAndConsole(string $titre, string $console): ?Game
    {
        return $this->createQueryBuilder('g')
            ->join('g.userLinks', 'link')
            ->leftJoin('link.consoleEntity', 'gc')
            ->where('g.titre = :titre')
            ->andWhere('gc.code = :console')
            ->setParameter('titre', $titre)
            ->setParameter('console', $console)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Codes console distincts présents dans les collections (FK game_console).
     *
     * @return list<string>
     */
    public function getDistinctConsoles(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DISTINCT gc.code AS code
                FROM lien_user_game lug
                INNER JOIN game_console gc ON gc.id = lug.console_id
                WHERE gc.code IS NOT NULL AND TRIM(gc.code) <> \'\'
                ORDER BY code ASC';

        return array_column($conn->executeQuery($sql)->fetchAllAssociative(), 'code');
    }

    public function getDistinctGenres(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('DISTINCT g.genre')
            ->where('g.genre IS NOT NULL')
            ->andWhere('g.genre != :empty')
            ->setParameter('empty', '')
            ->orderBy('g.genre', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'genre');
    }

    public function getDistinctYears(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select('DISTINCT g.annee')
            ->where('g.annee IS NOT NULL')
            ->orderBy('g.annee', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'annee');
    }

    public function countAll(): int
    {
        return $this->count([]);
    }
}
