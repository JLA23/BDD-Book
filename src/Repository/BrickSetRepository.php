<?php

namespace App\Repository;

use App\Entity\BrickSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BrickSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BrickSet::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.collection', 'c')
            ->leftJoin('s.marque', 'm')
            ->addSelect('c', 'm')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByReference(string $reference): ?BrickSet
    {
        return $this->createQueryBuilder('s')
            ->where('s.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function referenceExists(string $reference): bool
    {
        $result = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    public function search(string $search): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.collection', 'c')
            ->leftJoin('s.marque', 'm')
            ->addSelect('c', 'm')
            ->where('s.nom LIKE :search')
            ->orWhere('s.reference LIKE :search')
            ->orWhere('c.nom LIKE :search')
            ->orWhere('m.nom LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function createPaginationQueryBuilder(?string $search = null, ?int $collectionId = null, ?int $marqueId = null, ?int $userId = null)
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.collection', 'c')
            ->leftJoin('s.marque', 'm')
            ->addSelect('c', 'm')
            ->leftJoin('s.images', 'i')
            ->addSelect('i');

        if ($search) {
            $qb->andWhere('s.nom LIKE :search OR s.reference LIKE :search OR c.nom LIKE :search OR m.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($collectionId) {
            $qb->andWhere('s.collection = :collectionId')
               ->setParameter('collectionId', $collectionId);
        }

        if ($marqueId) {
            $qb->andWhere('s.marque = :marqueId')
               ->setParameter('marqueId', $marqueId);
        }

        if ($userId) {
            $qb->innerJoin('s.listeUser', 'lu')
               ->andWhere('lu.user = :userId')
               ->setParameter('userId', $userId);
        }

        return $qb->orderBy('s.nom', 'ASC');
    }
}
