<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Livre;
use App\Service\BookCoverService;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;


class LivresController extends AbstractController
{
    private BookCoverService $bookCoverService;
    private EntityManagerInterface $em;

    public function __construct(BookCoverService $bookCoverService, EntityManagerInterface $em)
    {
        $this->bookCoverService = $bookCoverService;
        $this->em = $em;
    }
    /**
     * @Route("/listelivre", name="listesLivres")
     */
    public function listesLivres(Request $request, PaginatorInterface $paginator)
    {
        $detect = new \Mobile_Detect;
        $em = $this->getDoctrine()->getManager();

        $listeLivreId = $em->getRepository(Livre::class)->getAllLivres($request->get('user'),$request->get('sort'), $request->get('order'));
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
            if ($livre) {
                // Privilégier image2 (fichier téléchargé) si disponible, sinon image (BLOB)
                if ($livre->getImage2()) {
                    $images[$livre->getId()] = '/uploads/covers/' . $livre->getImage2();
                } elseif ($livre->getImage()) {
                    $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
                }
            }
        }

        return $this->render('pages/listelivre.html.twig', ['pagination' => $page, 'Listelivres' => $listeLivre, 'images'=> $images, 'mobile' => $detect->isMobile()]) ;

    }

    /**
 * @Route("/livre/{id}", name="livreDetail")
 */
public function livreDetail(string $id, Request $request)
{
    $detect = new \Mobile_Detect;
    $em = $this->getDoctrine()->getManager();

    $livre = $em->getRepository(Livre::class)->findOneById($id);

    return $this->render('pages/livreDetail.html.twig', ['livre' => $livre, 'mobile' => $detect->isMobile()]) ;

}

    /**
     * @Route("/recherche", name="searchBook")
     */
    public function searchBook(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();
        $detect = new \Mobile_Detect;
        
        $users = $em->getRepository(\App\Entity\User::class)->findAll();

        if($request->query->has('value')){
            $search = $request->get('value');
            $user = $request->get('user');
            $userName = null;
            $userId = null;
            
            if ($user == '0' || empty($user)){
                $user = null;
            } else {
                $userId = (int) $user;
                $userEntity = $em->getRepository(\App\Entity\User::class)->find($user);
                if ($userEntity) {
                    $userName = $userEntity->getUsername();
                }
            }
            
            $listeLivreID = $em->getRepository(Livre::class)->getSearchLivre2($search, $request->get('sort'), $request->get('order'), $user);
            $images = array();
            
            // S'assurer que listeLivreID est un tableau
            if (!is_array($listeLivreID)) {
                $listeLivreID = [];
            }
            $totalResults = count($listeLivreID);
            
            if($listeLivreID && $totalResults > 0) {
                $page = $paginator->paginate(
                    $listeLivreID,
                    $request->query->getInt('page', 1),
                    100
                );
                $page->setCustomParameters([
                    'align' => 'center',
                    'style' => 'bottom',
                    'span_class' => 'whatever',
                ]);

                $listeLivre = $em->getRepository(Livre::class)->getLivresByID($page->getItems(), $request->get('sort'), $request->get('order'));

                foreach ($listeLivre as $livre) {
                    if ($livre) {
                        // Privilégier image2 (fichier téléchargé) si disponible, sinon image (BLOB)
                        if ($livre->getImage2()) {
                            $images[$livre->getId()] = '/uploads/covers/' . $livre->getImage2();
                        } elseif ($livre->getImage()) {
                            $images[$livre->getId()] = base64_encode(stream_get_contents($livre->getImage()));
                        }
                    }
                }

                return $this->render('pages/listelivre.html.twig', [
                    'pagination' => $page, 
                    'Listelivres' => $listeLivre, 
                    'images'=> $images, 
                    'mobile' => $detect->isMobile(),
                    'searchQuery' => $search,
                    'searchUser' => $userName,
                    'searchUserId' => $userId,
                    'users' => $users,
                    'totalResults' => $totalResults,
                    'isSearchResult' => true
                ]);
            }
            
            return $this->render('pages/listelivre.html.twig', [
                'pagination' => null, 
                'Listelivres' => [], 
                'images'=> [], 
                'mobile' => $detect->isMobile(),
                'searchQuery' => $search,
                'searchUser' => $userName,
                'searchUserId' => $userId,
                'users' => $users,
                'totalResults' => 0,
                'isSearchResult' => true
            ]);
        }
        
        $this->addFlash('warning', 'Veuillez entrer un terme de recherche');
        return $this->redirectToRoute('index');
    }

    /**
     * @Route("/listelivreUser/{id}", name="listelivreUser")
     */
    public function listesLivresbyUser(string $id, Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();
        $detect = new \Mobile_Detect;

        $listeLivreId = $em->getRepository(Livre::class)->getAllLivresByUser($id, $request->get('sort'), $request->get('order'));
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

        return $this->render('pages/listelivre.html.twig', ['pagination' => $page, 'Listelivres' => $listeLivre, 'images'=> $images, 'mobile' => $detect->isMobile()]) ;

    }

    /**
     * @Route("/livre/{id}/rechercher-couverture", name="livre_search_cover", requirements={"id"="\d+"})
     */
    public function searchCover(int $id): JsonResponse
    {
        $livre = $this->em->getRepository(Livre::class)->find($id);
        
        if (!$livre) {
            return new JsonResponse(['success' => false, 'message' => 'Livre non trouvé'], 404);
        }

        $images = [];
        
        // 1. Essayer de scraper l'URL Amazon/externe du livre si elle existe
        $amazonUrl = $livre->getAmazon();
        if (!empty($amazonUrl)) {
            $scrapedImages = $this->bookCoverService->scrapeImagesFromUrl($amazonUrl);
            foreach ($scrapedImages as $url) {
                $images[] = ['url' => $url, 'source' => 'Page produit'];
            }
        }

        // 2. Essayer via ISBN si disponible
        $isbn = $livre->getIsbn();
        if (!empty($isbn)) {
            $result = $this->bookCoverService->findBestCover($isbn);
            if ($result['url']) {
                $images[] = ['url' => $result['url'], 'source' => $result['source']];
            }
        }

        if (!empty($images)) {
            return new JsonResponse([
                'success' => true,
                'images' => $images,
                'message' => count($images) . ' image(s) trouvée(s)'
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Aucune image trouvée'
        ]);
    }

    /**
     * @Route("/livre/{id}/mettre-a-jour-couverture", name="livre_update_cover", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function updateCover(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $livre = $this->em->getRepository(Livre::class)->find($id);
        
        if (!$livre) {
            return new JsonResponse(['success' => false, 'message' => 'Livre non trouvé'], 404);
        }

        $imageUrl = $request->request->get('image_url');
        
        if (empty($imageUrl)) {
            return new JsonResponse(['success' => false, 'message' => 'URL de l\'image manquante']);
        }

        // Stocker l'URL au lieu de télécharger l'image
        $livre->setImageUrl($imageUrl);
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'URL de l\'image enregistrée avec succès',
            'imageUrl' => $imageUrl
        ]);
    }

    /**
     * @Route("/livre/{id}/supprimer-url-couverture", name="livre_remove_cover_url", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function removeCoverUrl(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $livre = $this->em->getRepository(Livre::class)->find($id);
        
        if (!$livre) {
            return new JsonResponse(['success' => false, 'message' => 'Livre non trouvé'], 404);
        }

        $livre->setImageUrl(null);
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'URL de l\'image supprimée, l\'image originale sera utilisée'
        ]);
    }

    /**
     * @Route("/livres/mettre-a-jour-toutes-couvertures", name="livres_update_all_covers")
     */
    public function updateAllCovers(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $livres = $this->em->getRepository(Livre::class)->findAll();
        $updated = 0;

        foreach ($livres as $livre) {
            $isbn = $livre->getIsbn();
            
            // Ne pas mettre à jour si déjà une URL ou pas d'ISBN
            if (empty($isbn) || !empty($livre->getImageUrl())) {
                continue;
            }

            $result = $this->bookCoverService->findBestCover($isbn);
            
            if ($result['url']) {
                $livre->setImageUrl($result['url']);
                $updated++;
            }
        }

        $this->em->flush();

        $this->addFlash('success', $updated . ' URL(s) de couverture(s) ajoutée(s)');
        
        return $this->redirectToRoute('listesLivres');
    }

    /**
     * @Route("/livre/{id}/scrape-covers", name="livre_scrape_covers")
     */
    public function scrapeCovers(string $id): JsonResponse
    {
        $livre = $this->em->getRepository(Livre::class)->findOneById($id);
        
        if (!$livre) {
            return new JsonResponse(['error' => 'Livre non trouvé'], 404);
        }

        $isbn = $livre->getIsbn();
        if (!$isbn) {
            return new JsonResponse(['error' => 'Ce livre n\'a pas d\'ISBN'], 400);
        }

        // Scraper toutes les sources via Puppeteer
        $images = $this->bookCoverService->findAllCovers($isbn);

        return new JsonResponse([
            'success' => true,
            'images' => $images,
            'isbn' => $isbn,
            'count' => count($images)
        ]);
    }

    /**
     * @Route("/livre/{id}/update-cover-url", name="livre_update_cover_url", methods={"POST"})
     */
    public function updateCoverUrl(string $id, Request $request): Response
    {
        $livre = $this->em->getRepository(Livre::class)->findOneById($id);
        
        if (!$livre) {
            return new JsonResponse(['error' => 'Livre non trouvé'], 404);
        }

        $imageUrl = $request->request->get('imageUrl');
        
        if (!$imageUrl) {
            return new JsonResponse(['error' => 'URL d\'image requise'], 400);
        }

        try {
            // Créer le répertoire si nécessaire
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/covers';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    return new JsonResponse(['error' => 'Impossible de créer le répertoire uploads/covers'], 500);
                }
            }

            // Vérifier que le répertoire est accessible en écriture
            if (!is_writable($uploadDir)) {
                return new JsonResponse(['error' => 'Le répertoire uploads/covers n\'est pas accessible en écriture'], 500);
            }

            // Télécharger l'image avec contexte pour gérer les redirections
            $context = stream_context_create([
                'http' => [
                    'follow_location' => true,
                    'max_redirects' => 5,
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $imageContent = @file_get_contents($imageUrl, false, $context);
            
            if ($imageContent === false) {
                $error = error_get_last();
                return new JsonResponse([
                    'error' => 'Impossible de télécharger l\'image',
                    'details' => $error ? $error['message'] : 'Erreur inconnue',
                    'url' => $imageUrl
                ], 500);
            }

            // Vérifier que le contenu est bien une image
            if (strlen($imageContent) < 100) {
                return new JsonResponse(['error' => 'Le contenu téléchargé ne semble pas être une image valide'], 500);
            }

            // Générer un nom de fichier unique
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extension = 'jpg';
            }
            
            $filename = $livre->getId() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . '/' . $filename;

            // Sauvegarder l'image
            $bytesWritten = @file_put_contents($filepath, $imageContent);
            if ($bytesWritten === false) {
                return new JsonResponse(['error' => 'Impossible de sauvegarder l\'image sur le disque'], 500);
            }

            // Supprimer l'ancienne image si elle existe
            if ($livre->getImage2()) {
                $oldFile = $uploadDir . '/' . $livre->getImage2();
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            // Mettre à jour le livre
            $livre->setImage2($filename);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Image de couverture mise à jour',
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
}
