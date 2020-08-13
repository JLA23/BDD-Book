<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Livre;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;


class LivresController extends AbstractController
{
    /**
     * @Route("/listelivre", name="listesLivres")
     */
    public function listesLivres(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();
        $listeLivre = $em->getRepository(Livre::class)->findBy([],['titre' => 'asc']);
        $images = array();
        foreach ($listeLivre as $livres) {
            if($livres && $livres->getImage()) {
                $images[$livres->getId()] = base64_encode(stream_get_contents($livres->getImage()));
            }
        }

        $livres = $paginator->paginate(
            $listeLivre, // Requête contenant les données à paginer (ici nos articles)
            $request->query->getInt('page', 1),
            25
        );
        $livres->setCustomParameters([
            'align' => 'center', # center|right (for template: twitter_bootstrap_v4_pagination)

            'style' => 'bottom',
            'span_class' => 'whatever',
        ]);
        return $this->render('pages/listelivre.html.twig', ['livres' => $livres, 'images'=> $images]) ;

    }
}