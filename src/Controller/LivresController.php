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

        $listeLivreId = $em->getRepository(Livre::class)->getAllLivres($request->get('sort'), $request->get('order'));
        $images = array();
        $page = $paginator->paginate(
            $listeLivreId,
            $request->query->getInt('page', 1),
            100
        );
        $page->setCustomParameters([
            'align' => 'center', # center|right (for template: twitter_bootstrap_v4_pagination)
            'style' => 'bottom',
            'span_class' => 'whatever',
        ]);

        $listeLivre = $em->getRepository(Livre::class)->getLivresByID($page->getItems(), $request->get('sort'), $request->get('order'));

       foreach ($listeLivre as $livre) {
            if ($livre && $livre->getImage()) {
                $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
            }
        }

        return $this->render('pages/listelivre.html.twig', ['pagination' => $page, 'Listelivres' => $listeLivre, 'images'=> $images]) ;

    }


    /**
     * @Route("/recherche", name="searchBook")
     */
    public function searchBook(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();

        if($request->query->has('value')){
            $search = $request->get('value');
            $listeLivreID = $em->getRepository(Livre::class)->getSearchLivre($search, $request->get('sort'), $request->get('order'));
            $images = array();
            dump($listeLivreID);
            if($listeLivreID && count($listeLivreID) > 0) {
                $page = $paginator->paginate(
                    $listeLivreID, // Requête contenant les données à paginer (ici nos articles)
                    $request->query->getInt('page', 1),
                    100
                );
                $page->setCustomParameters([
                    'align' => 'center', # center|right (for template: twitter_bootstrap_v4_pagination)
                    'style' => 'bottom',
                    'span_class' => 'whatever',
                ]);

                $listeLivre = $em->getRepository(Livre::class)->getLivresByID($page->getItems(), $request->get('sort'), $request->get('order'));

                foreach ($listeLivre as $livre) {
                    if ($livre && $livre->getImage()) {
                        $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
                    }
                }

                return $this->render('pages/listelivre.html.twig', ['pagination' => $page, 'Listelivres' => $listeLivre, 'images'=> $images]) ;
            }

        }
        $this->addFlash('warning', 'Aucun résultat pour votre recherche');
        return $this->redirectToRoute('index');

    }
}