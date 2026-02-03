<?php

namespace App\Controller;

use App\Entity\Auteur;
use App\Entity\LienAuteurLivre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuteurController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/auteurs', name: 'liste_auteurs')]
    public function listeAuteurs(Request $request): Response
    {
        $em = $this->em;
        
        $lettre = $request->query->get('lettre');
        $search = $request->query->get('search');
        
        // Récupérer toutes les initiales disponibles
        $initiales = $em->getRepository(Auteur::class)->findAllInitiales();
        
        // Si recherche
        if ($search) {
            $auteurs = $em->getRepository(Auteur::class)->searchAuteurs($search);
            $auteursGroupes = [];
            foreach ($auteurs as $auteur) {
                $initiale = $auteur->getInitiale();
                if (!isset($auteursGroupes[$initiale])) {
                    $auteursGroupes[$initiale] = [];
                }
                $auteursGroupes[$initiale][] = $auteur;
            }
            ksort($auteursGroupes);
        } elseif ($lettre) {
            // Filtrer par lettre
            $tousAuteurs = $em->getRepository(Auteur::class)->findAllGroupedByInitiale();
            $auteursGroupes = isset($tousAuteurs[$lettre]) ? [$lettre => $tousAuteurs[$lettre]] : [];
        } else {
            // Tous les auteurs groupés
            $auteursGroupes = $em->getRepository(Auteur::class)->findAllGroupedByInitiale();
        }
        
        // Compter les livres par auteur
        $livresParAuteur = [];
        foreach ($auteursGroupes as $initiale => $auteurs) {
            foreach ($auteurs as $auteur) {
                $count = $em->getRepository(LienAuteurLivre::class)->createQueryBuilder('l')
                    ->select('COUNT(l.id)')
                    ->where('l.auteur = :auteur')
                    ->setParameter('auteur', $auteur)
                    ->getQuery()
                    ->getSingleScalarResult();
                $livresParAuteur[$auteur->getId()] = $count;
            }
        }
        
        return $this->render('auteurs/liste.html.twig', [
            'auteursGroupes' => $auteursGroupes,
            'initiales' => $initiales,
            'lettreActive' => $lettre,
            'searchTerm' => $search,
            'livresParAuteur' => $livresParAuteur,
        ]);
    }

    #[Route('/auteur/{id}', name: 'auteur_detail', requirements: ['id' => '\d+'])]
    public function detailAuteur(int $id): Response
    {
        $em = $this->em;
        
        $auteur = $em->getRepository(Auteur::class)->find($id);
        
        if (!$auteur) {
            throw $this->createNotFoundException('Auteur non trouvé');
        }
        
        // Récupérer les livres de cet auteur
        $liens = $em->getRepository(LienAuteurLivre::class)->findBy(['auteur' => $auteur]);
        $livres = [];
        $images = [];
        
        foreach ($liens as $lien) {
            $livre = $lien->getLivre();
            $livres[] = $livre;
            if ($livre->getImage()) {
                $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
            }
        }
        
        return $this->render('auteurs/detail.html.twig', [
            'auteur' => $auteur,
            'livres' => $livres,
            'images' => $images,
        ]);
    }
}
