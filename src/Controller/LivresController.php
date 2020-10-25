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

        $listeLivreId = $em->getRepository(Livre::class)->getAllLivres();

        $images = array();


        $livres = $paginator->paginate(
            $listeLivreId, // Requête contenant les données à paginer (ici nos articles)
            $request->query->getInt('page', 1),
            15
        );
        $livres->setCustomParameters([
            'align' => 'center', # center|right (for template: twitter_bootstrap_v4_pagination)
            'style' => 'bottom',
            'span_class' => 'whatever',
        ]);

        $listeLivre = array();
        foreach ($livres->getItems() as $livreid) {
            $livre = $em->getRepository(Livre::class)->findOneBy(['id' => $livreid]);
            if ($livre && $livre->getImage()) {
                $listeLivre[$livre->getId()] = $livre;
                $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
            }
        }
        return $this->render('pages/listelivre.html.twig', ['livres' => $livres, 'llivres' => $listeLivre, 'images'=> $images]) ;

    }


    /**
     * @Route("/recherche", name="searchBook")
     */
    public function searchBook(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();

        if($request->query->has('value')){
            $search = $request->get('value');
            $listeLivreID = $em->getRepository(Livre::class)->getSearchLivre($search);
            $images = array();
            if(count($listeLivreID) > 0) {
                $livres = $paginator->paginate(
                    $listeLivreID, // Requête contenant les données à paginer (ici nos articles)
                    $request->query->getInt('page', 1),
                    15
                );
                $livres->setCustomParameters([
                    'align' => 'center', # center|right (for template: twitter_bootstrap_v4_pagination)
                    'style' => 'bottom',
                    'span_class' => 'whatever',
                ]);

                $listeLivre = array();
                foreach ($livres->getItems() as $livreid) {
                    $livre = $em->getRepository(Livre::class)->findOneBy(['id' => $livreid]);
                    if ($livre && $livre->getImage()) {
                        $listeLivre[$livre->getId()] = $livre;
                        $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
                    }
                }
                return $this->render('pages/listelivre.html.twig', ['livres' => $livres, 'llivres' => $listeLivre, 'images'=> $images]) ;
            }

        }

        return $this->redirectToRoute('index2');

    }
}
