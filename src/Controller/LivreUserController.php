<?php

namespace App\Controller;

use App\Entity\LienAuteurLivre;
use App\Entity\LienUserLivre;
use App\Entity\Livre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/livre/{id}', requirements: ['id' => '\d+'])]
class LivreUserController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Ajouter un utilisateur comme propriétaire d'un livre
     */
    #[Route('/ajouter-utilisateur', name: 'livre_ajouter_utilisateur', methods: ['GET', 'POST'])]
    public function ajouterUtilisateur(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $livre = $this->em->getRepository(Livre::class)->find($id);
        if (!$livre) {
            $this->addFlash('warning', 'Livre introuvable.');
            return $this->redirectToRoute('listesLivres');
        }

        $users = $this->em->getRepository(User::class)->findAll();

        // Filtrer les utilisateurs qui possèdent déjà ce livre
        $existingUserIds = [];
        foreach ($livre->getListeUser() as $lienUser) {
            $existingUserIds[] = $lienUser->getUser()->getId();
        }
        $availableUsers = array_filter($users, function ($user) use ($existingUserIds) {
            return !in_array($user->getId(), $existingUserIds);
        });

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id');
            $user = $this->em->getRepository(User::class)->find($userId);

            if (!$user) {
                $this->addFlash('warning', 'Utilisateur introuvable.');
                return $this->redirectToRoute('livre_ajouter_utilisateur', ['id' => $id]);
            }

            // Vérifier que le lien n'existe pas déjà
            $existing = $this->em->getRepository(LienUserLivre::class)->findOneBy([
                'user' => $user,
                'livre' => $livre,
            ]);
            if ($existing) {
                $this->addFlash('warning', 'Cet utilisateur possède déjà ce livre.');
                return $this->redirectToRoute('livreDetail', ['id' => $id]);
            }

            $lien = new LienUserLivre();
            $lien->setLivre($livre);
            $lien->setUser($user);

            $dateAchat = $request->request->get('dateAchat');
            if (!empty($dateAchat)) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }

            $commentaire = $request->request->get('commentaire');
            if (!empty($commentaire)) {
                $lien->setCommentaire($commentaire);
            }

            $this->em->persist($lien);
            $this->em->flush();

            $this->addFlash('warning', $user->getFullName() . ' a été ajouté comme propriétaire de "' . $livre->getTitre() . '".');
            return $this->redirectToRoute('livreDetail', ['id' => $id]);
        }

        return $this->render('livres/ajouter_utilisateur.html.twig', [
            'livre' => $livre,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Supprimer un utilisateur d'un livre
     * Si c'est le dernier propriétaire, proposer de supprimer le livre
     */
    #[Route('/supprimer-utilisateur/{lienId}', name: 'livre_supprimer_utilisateur', methods: ['POST'], requirements: ['lienId' => '\d+'])]
    public function supprimerUtilisateur(int $id, int $lienId, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $livre = $this->em->getRepository(Livre::class)->find($id);
        if (!$livre) {
            $this->addFlash('warning', 'Livre introuvable.');
            return $this->redirectToRoute('listesLivres');
        }

        $lien = $this->em->getRepository(LienUserLivre::class)->find($lienId);
        if (!$lien || $lien->getLivre()->getId() !== $livre->getId()) {
            $this->addFlash('warning', 'Lien utilisateur-livre introuvable.');
            return $this->redirectToRoute('livreDetail', ['id' => $id]);
        }

        $userName = $lien->getUser()->getFullName();
        $nbOwners = count($livre->getListeUser());

        // Si c'est le dernier propriétaire et que la suppression du livre est confirmée
        if ($nbOwners <= 1 && $request->request->get('delete_livre') === '1') {
            // Supprimer le lien user
            $this->em->remove($lien);

            // Supprimer les liens auteur
            $liensAuteur = $this->em->getRepository(LienAuteurLivre::class)->findBy(['livre' => $livre]);
            foreach ($liensAuteur as $la) {
                $this->em->remove($la);
            }

            // Supprimer l'image de couverture si elle existe
            if ($livre->getImage2()) {
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/covers/' . $livre->getImage2();
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $titreLivre = $livre->getTitre();
            $this->em->remove($livre);
            $this->em->flush();

            $this->addFlash('warning', 'Le livre "' . $titreLivre . '" a été supprimé car il n\'avait plus de propriétaire.');
            return $this->redirectToRoute('listesLivres');
        }

        // Si c'est le dernier propriétaire mais pas de confirmation → demander confirmation
        if ($nbOwners <= 1 && $request->request->get('delete_livre') !== '1') {
            return $this->render('livres/confirmer_suppression_livre.html.twig', [
                'livre' => $livre,
                'lien' => $lien,
            ]);
        }

        // Sinon, simplement supprimer le lien
        $this->em->remove($lien);
        $this->em->flush();

        $this->addFlash('warning', $userName . ' a été retiré des propriétaires de "' . $livre->getTitre() . '".');
        return $this->redirectToRoute('livreDetail', ['id' => $id]);
    }

    /**
     * Transférer un livre d'un utilisateur à un autre
     */
    #[Route('/transferer/{lienId}', name: 'livre_transferer', methods: ['GET', 'POST'], requirements: ['lienId' => '\d+'])]
    public function transferer(int $id, int $lienId, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $livre = $this->em->getRepository(Livre::class)->find($id);
        if (!$livre) {
            $this->addFlash('warning', 'Livre introuvable.');
            return $this->redirectToRoute('listesLivres');
        }

        $lien = $this->em->getRepository(LienUserLivre::class)->find($lienId);
        if (!$lien || $lien->getLivre()->getId() !== $livre->getId()) {
            $this->addFlash('warning', 'Lien utilisateur-livre introuvable.');
            return $this->redirectToRoute('livreDetail', ['id' => $id]);
        }

        $currentUser = $lien->getUser();

        // Utilisateurs disponibles pour le transfert (tous sauf l'actuel propriétaire sur ce lien)
        $users = $this->em->getRepository(User::class)->findAll();
        $existingUserIds = [];
        foreach ($livre->getListeUser() as $lu) {
            $existingUserIds[] = $lu->getUser()->getId();
        }
        $availableUsers = array_filter($users, function ($user) use ($existingUserIds) {
            return !in_array($user->getId(), $existingUserIds);
        });

        if ($request->isMethod('POST')) {
            $targetUserId = $request->request->get('target_user_id');
            $targetUser = $this->em->getRepository(User::class)->find($targetUserId);

            if (!$targetUser) {
                $this->addFlash('warning', 'Utilisateur cible introuvable.');
                return $this->redirectToRoute('livre_transferer', ['id' => $id, 'lienId' => $lienId]);
            }

            // Vérifier que l'utilisateur cible ne possède pas déjà ce livre
            $existingLink = $this->em->getRepository(LienUserLivre::class)->findOneBy([
                'user' => $targetUser,
                'livre' => $livre,
            ]);
            if ($existingLink) {
                $this->addFlash('warning', $targetUser->getFullName() . ' possède déjà ce livre.');
                return $this->redirectToRoute('livreDetail', ['id' => $id]);
            }

            // Transférer : changer l'utilisateur du lien
            $fromName = $currentUser->getFullName();
            $lien->setUser($targetUser);
            $this->em->flush();

            $this->addFlash('warning', 'Le livre "' . $livre->getTitre() . '" a été transféré de ' . $fromName . ' à ' . $targetUser->getFullName() . '.');
            return $this->redirectToRoute('livreDetail', ['id' => $id]);
        }

        return $this->render('livres/transferer_livre.html.twig', [
            'livre' => $livre,
            'lien' => $lien,
            'currentUser' => $currentUser,
            'availableUsers' => $availableUsers,
        ]);
    }
}
