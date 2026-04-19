<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/utilisateurs', name: 'admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepo->findAll();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'sections' => User::getAvailableSections(),
        ]);
    }

    #[Route('/utilisateur/{id}/permissions', name: 'admin_user_permissions', requirements: ['id' => '\d+'])]
    public function userPermissions(int $id, Request $request, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        if ($request->isMethod('POST')) {
            $sections = $request->request->all('sections');
            
            // Si toutes les sections sont cochées ou aucune restriction, on met null
            if (empty($sections)) {
                $user->setSectionsEnregistrement([]);
            } elseif (count($sections) === count(User::SECTIONS)) {
                $user->setSectionsEnregistrement(null); // Accès à tout
            } else {
                $user->setSectionsEnregistrement($sections);
            }

            $em->flush();
            $this->addFlash('success', 'Permissions mises à jour pour ' . $user->getFullName());

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_permissions.html.twig', [
            'user' => $user,
            'sections' => User::getAvailableSections(),
        ]);
    }

    #[Route('/utilisateur/{id}/toggle-admin', name: 'admin_toggle_admin', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleAdmin(int $id, Request $request, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Ne pas permettre de se retirer soi-même le rôle admin
        if ($user === $this->getUser()) {
            $this->addFlash('warning', 'Vous ne pouvez pas modifier votre propre rôle admin');
            return $this->redirectToRoute('admin_users');
        }

        if ($this->isCsrfTokenValid('toggle-admin-' . $id, $request->request->get('_token'))) {
            $roles = $user->getRoles();
            
            if (in_array('ROLE_ADMIN', $roles)) {
                // Retirer le rôle admin
                $roles = array_filter($roles, fn($r) => $r !== 'ROLE_ADMIN' && $r !== 'ROLE_USER');
                $user->setRoles(array_values($roles));
                $this->addFlash('success', $user->getFullName() . ' n\'est plus administrateur');
            } else {
                // Ajouter le rôle admin
                $roles[] = 'ROLE_ADMIN';
                $user->setRoles(array_unique($roles));
                $this->addFlash('success', $user->getFullName() . ' est maintenant administrateur');
            }

            $em->flush();
        }

        return $this->redirectToRoute('admin_users');
    }
}
