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
        $qb = $this->createQueryBuilder('g');

        if ($search) {
            $qb->andWhere('g.titre LIKE :search OR g.editeur LIKE :search OR g.developpeur LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($console) {
            // Recherche par console via les liens utilisateur uniquement
            $qb->leftJoin('g.userLinks', 'link')
               ->andWhere('link.console = :console')
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
        // Chercher un jeu avec ce titre ayant déjà un lien sur cette console
        return $this->createQueryBuilder('g')
            ->join('g.userLinks', 'link')
            ->where('g.titre = :titre')
            ->andWhere('link.console = :console')
            ->setParameter('titre', $titre)
            ->setParameter('console', $console)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getDistinctConsoles(): array
    {
        // Consoles depuis les liens utilisateur uniquement
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DISTINCT console FROM lien_user_game WHERE console IS NOT NULL AND console != '' ORDER BY console ASC";
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_column($result, 'console');
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
