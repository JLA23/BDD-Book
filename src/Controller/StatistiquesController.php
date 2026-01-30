<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Entity\KioskCollec;
use App\Entity\KioskNum;
use App\Entity\LienUserLivre;
use App\Entity\LienKioskNumUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatistiquesController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/statistiques", name="statistiques")
     */
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Statistiques des livres de l'utilisateur
        $userLivresCount = $this->em->createQueryBuilder()
            ->select('COUNT(lul.id)')
            ->from(LienUserLivre::class, 'lul')
            ->where('lul.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
            
        $userLivresPrix = $this->em->createQueryBuilder()
            ->select('SUM(l.prixBase)')
            ->from(LienUserLivre::class, 'lul')
            ->join('lul.livre', 'l')
            ->where('lul.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Statistiques des numéros de l'utilisateur
        $userNumerosCount = $this->em->createQueryBuilder()
            ->select('COUNT(lknu.id)')
            ->from(LienKioskNumUser::class, 'lknu')
            ->where('lknu.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
            
        $userNumerosPrix = $this->em->createQueryBuilder()
            ->select('SUM(n.prix)')
            ->from(LienKioskNumUser::class, 'lknu')
            ->join('lknu.kioskNum', 'n')
            ->where('lknu.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
            
        $userMagazinesCount = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT kc.id)')
            ->from(LienKioskNumUser::class, 'lknu')
            ->join('lknu.kioskNum', 'n')
            ->join('n.kioskCollec', 'kc')
            ->where('lknu.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('pages/statistiques.html.twig', [
            'userLivresCount' => $userLivresCount,
            'userLivresPrix' => $userLivresPrix,
            'userMagazinesCount' => $userMagazinesCount,
            'userNumerosCount' => $userNumerosCount,
            'userNumerosPrix' => $userNumerosPrix,
        ]);
    }
}
