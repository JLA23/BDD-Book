<?php

namespace App\Repository;

use App\Entity\LienUserGame;
use App\Entity\User;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LienUserGame>
 */
class LienUserGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LienUserGame::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.game', 'g')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByGame(Game $game): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->where('l.game = :game')
            ->setParameter('game', $game)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->count(['user' => $user]);
    }

    public function userHasGame(User $user, Game $game): bool
    {
        return $this->count(['user' => $user, 'game' => $game]) > 0;
    }

    public function findUserGameLink(User $user, Game $game): ?LienUserGame
    {
        return $this->findOneBy(['user' => $user, 'game' => $game]);
    }
}
