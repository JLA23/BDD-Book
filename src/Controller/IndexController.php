<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;


class IndexController extends AbstractController
{
    /**
     * @Route("/listeUser", name="listeUser")
     */
    public function listeUser()
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository(User::class)->findAll();
        return $this->render('pages/listUser.html.twig', ['users' => $users]) ;
    }

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        return $this->render('pages/index.html.twig') ;
    }
}