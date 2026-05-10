<?php

namespace App\Repository;

use App\Entity\MusiqueUserCollection;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusiqueUserCollection>
 */
class MusiqueUserCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusiqueUserCollection::class);
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('muc')
            ->select('COUNT(muc.id)')
            ->where('muc.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('muc')
            ->leftJoin('muc.musique', 'm')
            ->addSelect('m')
            ->where('muc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.artiste', 'ASC')
            ->addOrderBy('m.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMusique(\App\Entity\Musique $musique): array
    {
        return $this->createQueryBuilder('muc')
            ->leftJoin('muc.user', 'u')
            ->addSelect('u')
            ->where('muc.musique = :musique')
            ->setParameter('musique', $musique)
            ->getQuery()
            ->getResult();
    }
}
