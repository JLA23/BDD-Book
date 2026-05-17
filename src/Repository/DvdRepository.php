<?php

namespace App\Repository;

use App\Entity\Dvd;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dvd>
 */
class DvdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dvd::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findBySearch(?string $search, ?string $format, ?string $userId, ?string $year)
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.userLinks', 'ul')
            ->leftJoin('ul.user', 'u');

        if ($search) {
            $conditions = [
                'LOWER(d.titre) LIKE LOWER(:search)',
                'LOWER(d.editeur) LIKE LOWER(:search)',
                'd.ean LIKE :search',
            ];
            $eanDigits = preg_replace('/\D/', '', $search);
            if ($eanDigits !== '') {
                $conditions[] = 'd.ean LIKE :eanDigits';
            }
            $qb->andWhere(implode(' OR ', $conditions))
               ->setParameter('search', '%' . $search . '%');
            if ($eanDigits !== '') {
                $qb->setParameter('eanDigits', '%' . $eanDigits . '%');
            }
        }

        if ($format) {
            $qb->andWhere('d.format = :format')
               ->setParameter('format', $format);
        }

        if ($userId) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', $userId);
        }

        if ($year) {
            $qb->andWhere('d.annee = :year')
               ->setParameter('year', (int) $year);
        }

        $qb->orderBy('d.titre', 'ASC');

        return $qb->getQuery();
    }

    public function getDistinctYears(): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('DISTINCT d.annee')
            ->where('d.annee IS NOT NULL')
            ->orderBy('d.annee', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'annee');
    }
}
