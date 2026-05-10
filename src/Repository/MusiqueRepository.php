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
}
