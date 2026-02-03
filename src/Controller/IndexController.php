<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\UserRepository;

class IndexController extends AbstractController
{
    #[Route('/listeUser', name: 'listeUser')]
    public function listeUser(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        return $this->render('pages/listUser.html.twig', ['users' => $users]);
    }

    #[Route('/', name: 'index')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        return $this->render('pages/index.html.twig', ['users' => $users]);
    }
}