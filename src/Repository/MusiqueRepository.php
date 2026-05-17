<?php

namespace App\Repository;

use App\Entity\Musique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Musique>
 */
class MusiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Musique::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findBySearch(?string $search, ?string $format, ?string $userId, ?string $year)
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.userLinks', 'ul')
            ->leftJoin('ul.user', 'u');

        if ($search) {
            $qb->andWhere('LOWER(m.titre) LIKE LOWER(:search) OR LOWER(m.artiste) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($format) {
            $qb->andWhere('m.format = :format')
               ->setParameter('format', $format);
        }

        if ($userId) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', $userId);
        }

        if ($year) {
            $qb->andWhere('m.annee = :year')
               ->setParameter('year', (int) $year);
        }

        $qb->orderBy('m.artiste', 'ASC')
           ->addOrderBy('m.titre', 'ASC');

        return $qb->getQuery();
    }

    public function getDistinctYears(): array
    {
        $results = $this->createQueryBuilder('m')
            ->select('DISTINCT m.annee')
            ->where('m.annee IS NOT NULL')
            ->orderBy('m.annee', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'annee');
    }

    /**
     * Recherche les doublons potentiels par EAN, ou par titre+format+artiste
     */
    public function findDuplicates(?string $titre, ?string $format, ?string $artiste, ?string $ean): array
    {
        // Priorité 1: recherche par EAN si disponible
        if ($ean) {
            $results = $this->createQueryBuilder('m')
                ->where('m.ean = :ean')
                ->setParameter('ean', $ean)
                ->getQuery()
                ->getResult();
            
            if (count($results) > 0) {
                return $results;
            }
        }

        // Priorité 2: recherche par titre + format + artiste
        if ($titre && $format) {
            $qb = $this->createQueryBuilder('m')
                ->where('LOWER(m.titre) = LOWER(:titre)')
                ->andWhere('m.format = :format')
                ->setParameter('titre', $titre)
                ->setParameter('format', $format);

            if ($artiste) {
                $qb->andWhere('LOWER(m.artiste) = LOWER(:artiste)')
                   ->setParameter('artiste', $artiste);
            }

            return $qb->getQuery()->getResult();
        }

        return [];
    }

    /**
     * Recherche par EAN uniquement
     */
    public function findByEan(string $ean): ?Musique
    {
        return $this->createQueryBuilder('m')
            ->where('m.ean = :ean')
            ->setParameter('ean', $ean)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
