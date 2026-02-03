<?php

namespace App\Controller;

use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private string $avatarDirectory;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, string $projectDir)
    {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
        $this->avatarDirectory = $projectDir . '/public/uploads/avatars';
    }

    #[Route('/profil', name: 'user_profile')]
    public function profile(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->em->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route('/profil/upload-avatar', name: 'profile_upload_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        $file = $request->files->get('avatar');
        
        if (!$file) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun fichier reçu']);
        }
        
        // Vérifier le type MIME
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return new JsonResponse(['success' => false, 'message' => 'Type de fichier non autorisé']);
        }
        
        // Vérifier la taille (max 2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return new JsonResponse(['success' => false, 'message' => 'Fichier trop volumineux (max 2MB)']);
        }
        
        // Créer le dossier si nécessaire
        if (!is_dir($this->avatarDirectory)) {
            mkdir($this->avatarDirectory, 0755, true);
        }
        
        // Supprimer l'ancien avatar si existant
        if ($user->getLogo()) {
            $oldFile = $this->avatarDirectory . '/' . $user->getLogo();
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
        
        // Générer un nom unique
        $extension = $file->guessExtension() ?: 'jpg';
        $filename = 'avatar_' . $user->getId() . '_' . uniqid() . '.' . $extension;
        
        // Déplacer le fichier
        try {
            $file->move($this->avatarDirectory, $filename);
            
            // Mettre à jour l'utilisateur
            $user->setLogo($filename);
            $this->em->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Photo mise à jour',
                'avatarUrl' => '/uploads/avatars/' . $filename
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload']);
        }
    }

    #[Route('/profil/remove-avatar', name: 'profile_remove_avatar', methods: ['POST'])]
    public function removeAvatar(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Supprimer le fichier
        if ($user->getLogo()) {
            $file = $this->avatarDirectory . '/' . $user->getLogo();
            if (file_exists($file)) {
                unlink($file);
            }
            
            $user->setLogo(null);
            $this->em->flush();
        }
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Photo supprimée',
            'initiales' => $user->getInitiales()
        ]);
    }
}
