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
        foreach ($livres->getItems() as $livre) {
            if($livre && $livre->getImage()) {
                $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
            }
        }
        return $this->render('pages/listelivre.html.twig', ['livres' => $livres, 'images'=> $images]) ;

    }


    /**
     * @Route("/recherche", name="searchBook")
     */
    public function searchBook(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();

        if($request->query->has('value')){
            $search = $request->get('value');
            $listeLivre = $em->getRepository(Livre::class)->getSearchLivre($search);
            $images = array();
            if(count($listeLivre) > 0) {
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
                
                foreach ($livres->getItems() as $livre) {
		 	if($livre && $livre->getImage()) {
		        	$images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
			}
        	}
                return $this->render('pages/listelivre.html.twig', ['livres' => $livres, 'images'=> $images]) ;
            }

        }

        return $this->redirectToRoute('index2');

    }
}
