<?php

namespace App\Repository;

/**
 * LivreRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LivreRepository extends \Doctrine\ORM\EntityRepository
{
    public function getLivreByInfos($name, $isbn, $edition_id){
        $req = $this->createQueryBuilder('l')
            ->where('UPPER(l.titre) = :name')
            ->andWhere('l.edition_id = :edition_id')
            ->setParameter(':name', $name)
            ->distinct('l.Particularite')
            ->setParameter(':edition_id', $edition_id);
        if ($isbn){
            $req->orWhere('l.isbn = :isbn')
                ->setParameter(':isbn', $isbn);
        }
        return $req->getQuery()->getOneOrNullResult();
    }
}
