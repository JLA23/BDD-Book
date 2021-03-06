<?php

namespace App\Repository;

/**
 * LienUserLivreRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LienUserLivreRepository extends \Doctrine\ORM\EntityRepository
{
    public function getLivreByUser($user){
        return $this->createQueryBuilder('lul')
            ->innerJoin('lul.livre', 'l')
            ->where('lul.user = :user')
            ->setParameter(':user', $user)
            ->orderBy('l.titre', 'ASC')
            ->getQuery()->getResult();
    }

    public function getLivreByUserAndSeq ($user, $seq){
        return $this->createQueryBuilder('lul')
            ->innerJoin('lul.livre', 'l')
            ->where('lul.user = :user')
            ->andWhere('lul.seq = :seq')
            ->setParameter(':user', $user)
            ->setParameter(':seq', $seq)
            ->getQuery()->getOneOrNullResult();
    }

    public function getLienByUserAndLivre($user, $livre, $seq){
        return $this->createQueryBuilder('lul')
            ->where('lul.user = :user')
            ->andWhere('lul.livre = :livre')
            ->andWhere('lul.seq = :seq')
            ->setParameter(':user', $user)
            ->setParameter(':livre', $livre)
            ->setParameter(':seq', $seq)
            ->getQuery()->getOneOrNullResult();
    }

    public function getListeUserByLivre($livre){
        return $this->createQueryBuilder('lul')
            ->where('lul.livre = :livre')
            ->setParameter(':livre', $livre)
            ->getQuery()->getResult();
    }
}
