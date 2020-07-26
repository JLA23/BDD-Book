<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;


class IndexController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index()
    {

        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository(User::class)->findAll();
        dump($users);
        return $this->render('pages/index.html.twig', ['users' => $users]) ;
    }
}