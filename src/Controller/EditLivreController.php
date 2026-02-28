<?php

namespace App\Controller;

use App\Entity\Auteur;
use App\Entity\Category;
use App\Entity\Collection;
use App\Entity\Edition;
use App\Entity\LienAuteurLivre;
use App\Entity\LienUserLivre;
use App\Entity\Livre;
use App\Form\LivreType;
use App\Service\BookCoverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EditLivreController extends AbstractController
{
    private EntityManagerInterface $em;
    private BookCoverService $coverService;

    public function __construct(
        EntityManagerInterface $em,
        BookCoverService $coverService
    ) {
        $this->em = $em;
        $this->coverService = $coverService;
    }

    /**
     * Éditer un livre (uniquement si propriétaire)
     */
    #[Route('/livre/{id}/modifier', name: 'livre_modifier', methods: ['GET', 'POST'])]
    public function modifier(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $livre = $this->em->getRepository(Livre::class)->find($id);
        if (!$livre) {
            $this->addFlash('warning', 'Livre introuvable.');
            return $this->redirectToRoute('listesLivres');
        }

        // Vérifier que l'utilisateur est propriétaire
        $lienUser = $this->em->getRepository(LienUserLivre::class)->findOneBy([
            'user' => $this->getUser(),
            'livre' => $livre,
        ]);
        if (!$lienUser) {
            $this->addFlash('warning', 'Vous ne pouvez modifier que les livres de votre bibliothèque.');
            return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
        }

        $form = $this->createForm(LivreType::class, $livre);

        // Pré-remplir les champs non-mappés
        $auteurNoms = [];
        foreach ($livre->getListeAuteur() as $lienAuteur) {
            $auteurNoms[] = $lienAuteur->getAuteur()->getNom();
        }
        $form->get('auteurs')->setData(implode(', ', $auteurNoms));

        if ($livre->getImage2()) {
            $form->get('imageUrl')->setData('/uploads/covers/' . $livre->getImage2());
        }

        // Pré-remplir les champs LienUserLivre
        if ($lienUser->getDateAchat()) {
            $form->get('dateAchat')->setData($lienUser->getDateAchat());
        }
        if ($lienUser->getPrixAchat()) {
            $form->get('prixAchat')->setData($lienUser->getPrixAchat());
        }
        if ($lienUser->getCommentaire()) {
            $form->get('commentaire')->setData($lienUser->getCommentaire());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->saveEdit($form, $livre, $lienUser);
        }

        // Image actuelle pour affichage
        $currentImage = $livre->getBestImage();

        return $this->render('livres/modifier_formulaire.html.twig', [
            'form' => $form->createView(),
            'livre' => $livre,
            'imageUrl' => $currentImage,
            'lienUser' => $lienUser,
        ]);
    }

    private function saveEdit($form, Livre $livre, LienUserLivre $lienUser): Response
    {
        // Gérer les nouvelles catégorie/collection/éditeur
        $this->handleNewEntities($form, $livre);

        // Gérer l'image uploadée ou URL
        $imageFile = $form->get('imageFile')->getData();
        $imageUrl = $form->get('imageUrl')->getData();

        if ($imageFile) {
            $filename = uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/covers',
                $filename
            );
            $livre->setImage2($filename);
        } elseif (!empty($imageUrl) && !str_starts_with($imageUrl, '/uploads/')) {
            // URL externe → télécharger l'image
            $imageContent = $this->coverService->downloadImage($imageUrl);
            if ($imageContent) {
                $extension = 'jpg';
                if (preg_match('/\.(png|gif|webp|jpeg)/i', $imageUrl, $m)) {
                    $extension = strtolower($m[1]);
                }
                $filename = uniqid() . '.' . $extension;
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/covers/' . $filename;
                file_put_contents($path, $imageContent);
                $livre->setImage2($filename);
            }
        }

        // Mettre à jour les auteurs : supprimer les anciens liens, recréer
        $oldAuteurs = $this->em->getRepository(LienAuteurLivre::class)->findBy(['livre' => $livre]);
        foreach ($oldAuteurs as $oldLien) {
            $this->em->remove($oldLien);
        }

        $auteursStr = $form->get('auteurs')->getData();
        if (!empty($auteursStr)) {
            $auteurNoms = array_map('trim', explode(',', $auteursStr));
            foreach ($auteurNoms as $nom) {
                if (empty($nom)) continue;

                $auteur = $this->em->getRepository(Auteur::class)->findOneBy(['nom' => $nom]);
                if (!$auteur) {
                    $auteur = new Auteur();
                    $auteur->setNom($nom);
                    $this->em->persist($auteur);
                }

                $lien = new LienAuteurLivre();
                $lien->setLivre($livre);
                $lien->setAuteur($auteur);
                $this->em->persist($lien);
            }
        }

        // Mettre à jour le LienUserLivre
        $dateAchat = $form->get('dateAchat')->getData();
        $lienUser->setDateAchat($dateAchat);

        $prixAchat = $form->get('prixAchat')->getData();
        $lienUser->setPrixAchat($prixAchat ? (float) $prixAchat : null);

        $commentaire = $form->get('commentaire')->getData();
        $lienUser->setCommentaire($commentaire);

        $this->em->flush();

        $this->addFlash('warning', 'Le livre "' . $livre->getTitre() . '" a été modifié avec succès !');

        return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
    }

    /**
     * Gère la création de nouvelles catégories, collections et éditeurs depuis le formulaire
     */
    private function handleNewEntities($form, Livre $livre): void
    {
        $newCat = $form->get('newCategory')->getData();
        if (!empty($newCat)) {
            $existing = $this->em->getRepository(Category::class)->findOneBy(['nom' => trim($newCat)]);
            if ($existing) {
                $livre->setCategory($existing);
            } else {
                $cat = new Category();
                $cat->setNom(trim($newCat));
                $this->em->persist($cat);
                $livre->setCategory($cat);
            }
        }

        $newColl = $form->get('newCollection')->getData();
        if (!empty($newColl)) {
            $existing = $this->em->getRepository(Collection::class)->findOneBy(['nom' => trim($newColl)]);
            if ($existing) {
                $livre->setCollection($existing);
            } else {
                $coll = new Collection();
                $coll->setNom(trim($newColl));
                $this->em->persist($coll);
                $livre->setCollection($coll);
            }
        }

        $newEd = $form->get('newEdition')->getData();
        if (!empty($newEd)) {
            $existing = $this->em->getRepository(Edition::class)->findOneBy(['nom' => trim($newEd)]);
            if ($existing) {
                $livre->setEdition($existing);
            } else {
                $ed = new Edition();
                $ed->setNom(trim($newEd));
                $this->em->persist($ed);
                $livre->setEdition($ed);
            }
        }
    }
}
