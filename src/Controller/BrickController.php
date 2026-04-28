<?php

namespace App\Controller;

use App\Entity\BrickCollection;
use App\Entity\BrickImage;
use App\Entity\BrickMarque;
use App\Entity\BrickSet;
use App\Entity\LienUserBrickSet;
use App\Form\BrickCollectionType;
use App\Form\BrickMarqueType;
use App\Form\BrickSetType;
use App\Repository\BrickCollectionRepository;
use App\Repository\BrickImageRepository;
use App\Repository\BrickMarqueRepository;
use App\Repository\BrickSetRepository;
use App\Repository\LienUserBrickSetRepository;
use App\Repository\UserRepository;
use App\Service\BrickApiService;
use App\Service\SectionPermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/brick')]
class BrickController extends AbstractController
{
    private EntityManagerInterface $em;
    private BrickApiService $brickApi;
    private SectionPermissionService $permissionService;

    public function __construct(EntityManagerInterface $em, BrickApiService $brickApi, SectionPermissionService $permissionService)
    {
        $this->em = $em;
        $this->brickApi = $brickApi;
        $this->permissionService = $permissionService;
    }

    /**
     * Nettoie les collections et marques orphelines (sans sets associés)
     */
    private function cleanOrphanEntities(BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo): void
    {
        $hasChanges = false;

        // Supprimer les collections sans sets
        $allCollections = $collectionRepo->findAll();
        foreach ($allCollections as $collection) {
            if ($collection->getSets()->count() === 0) {
                $this->em->remove($collection);
                $hasChanges = true;
            }
        }

        // Supprimer les marques sans sets
        $allMarques = $marqueRepo->findAll();
        foreach ($allMarques as $marque) {
            if ($marque->getSets()->count() === 0) {
                $this->em->remove($marque);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->em->flush();
        }
    }

    #[Route('/', name: 'brick_index')]
    public function index(BrickSetRepository $setRepo, BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo, UserRepository $userRepo, LienUserBrickSetRepository $lienRepo): Response
    {
        // Récupérer les utilisateurs avec leur nombre de sets (ceux avec contenu ou permission)
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->count(['user' => $user]);
            if ($count > 0 || $user->canRegisterInSection('brick')) {
                $user->setsCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('brick/index.html.twig', [
            'totalSets' => $setRepo->count([]),
            'totalCollections' => $collectionRepo->count([]),
            'totalMarques' => $marqueRepo->count([]),
            'collections' => $collectionRepo->findAllOrdered(),
            'marques' => $marqueRepo->findAllOrdered(),
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/utilisateurs', name: 'brick_users')]
    public function users(UserRepository $userRepo, LienUserBrickSetRepository $lienRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->count(['user' => $user]);
            // Afficher si l'utilisateur a du contenu OU s'il a la permission d'enregistrer
            if ($count > 0 || $user->canRegisterInSection('brick')) {
                $user->setsCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('brick/users.html.twig', [
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/liste', name: 'brick_list')]
    public function list(Request $request, PaginatorInterface $paginator, BrickSetRepository $setRepo, BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo, UserRepository $userRepo): Response
    {
        $search = $request->query->get('search');
        $collectionId = $request->query->getInt('collection');
        $marqueId = $request->query->getInt('marque');
        $userId = $request->query->getInt('user');

        $queryBuilder = $setRepo->createPaginationQueryBuilder($search, $collectionId ?: null, $marqueId ?: null, $userId ?: null);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            24
        );

        return $this->render('brick/list.html.twig', [
            'pagination' => $pagination,
            'collections' => $collectionRepo->findAllOrdered(),
            'marques' => $marqueRepo->findAllOrdered(),
            'users' => $userRepo->findAll(),
            'search' => $search,
            'selectedCollection' => $collectionId,
            'selectedMarque' => $marqueId,
            'selectedUser' => $userId,
        ]);
    }

    #[Route('/set/{id}', name: 'brick_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, BrickSetRepository $setRepo): Response
    {
        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        return $this->render('brick/detail.html.twig', [
            'set' => $set,
        ]);
    }

    #[Route('/ajouter', name: 'brick_ajouter')]
    public function ajouter(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        return $this->render('brick/ajouter_choix.html.twig', [
            'rebrickableConfigured' => $this->brickApi->isRebrickableConfigured(),
            'bricksetConfigured' => $this->brickApi->isBricksetConfigured(),
        ]);
    }

    #[Route('/nouveau', name: 'brick_new')]
    public function new(Request $request, BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo, BrickSetRepository $setRepo, LienUserBrickSetRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        $session = $request->getSession();
        $set = new BrickSet();
        $prefillImages = [];
        $duplicateWarning = null;

        // Si pas de pré-remplissage, vider les images en session
        if (!$request->query->get('prefill')) {
            $session->remove('brick_prefill_images');
        }

        // Pré-remplissage depuis la recherche par référence
        if ($request->query->get('prefill')) {
            $set->setNom($request->query->get('nom', ''));
            $set->setReference($request->query->get('reference', ''));
            
            if ($request->query->get('annee')) {
                $set->setAnnee((int) $request->query->get('annee'));
            }
            if ($request->query->get('nbPieces')) {
                $set->setNbPieces((int) $request->query->get('nbPieces'));
            }
            if ($request->query->get('prix')) {
                $set->setPrix((float) $request->query->get('prix'));
            }

            // Vérifier si la référence existe déjà
            $reference = $request->query->get('reference', '');
            if ($reference && $setRepo->referenceExists($reference)) {
                $existingSet = $setRepo->findByReference($reference);
                $duplicateWarning = [
                    'message' => 'Un set avec cette référence existe déjà !',
                    'set' => $existingSet,
                ];
            }

            // Chercher ou créer la collection si thème fourni
            $prefillTheme = $request->query->get('theme');
            if ($prefillTheme) {
                $collection = $collectionRepo->findOneBy(['nom' => $prefillTheme]);
                if (!$collection) {
                    $collection = new BrickCollection();
                    $collection->setNom($prefillTheme);
                    $this->em->persist($collection);
                    $this->em->flush();
                }
                $set->setCollection($collection);
            }

            // Marque LEGO par défaut si pas spécifié
            $marque = $marqueRepo->findOneBy(['nom' => 'LEGO']);
            if (!$marque) {
                $marque = new BrickMarque();
                $marque->setNom('LEGO');
                $marque->setSiteWeb('https://www.lego.com');
                $this->em->persist($marque);
                $this->em->flush();
            }
            $set->setMarque($marque);
        }

        // Récupérer les images de la session
        $prefillImages = $session->get('brick_prefill_images', []);

        $form = $this->createForm(BrickSetType::class, $set);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier doublon avant de sauvegarder (avec et sans suffixe -1)
            $reference = $set->getReference();
            $refWithSuffix = preg_match('/-\d+$/', $reference) ? $reference : $reference . '-1';
            $refWithoutSuffix = preg_replace('/-\d+$/', '', $reference);
            
            $existingSet = $setRepo->findByReference($reference) 
                ?? $setRepo->findByReference($refWithSuffix)
                ?? $setRepo->findByReference($refWithoutSuffix);
            
            if ($existingSet) {
                $this->addFlash('danger', 'Un set avec cette référence existe déjà !');
                return $this->render('brick/form.html.twig', [
                    'form' => $form->createView(),
                    'set' => null,
                    'isEdit' => false,
                    'prefillImages' => $prefillImages,
                    'duplicateWarning' => [
                        'message' => 'Un set avec cette référence existe déjà !',
                        'set' => $existingSet,
                    ],
                ]);
            }

            $this->em->persist($set);

            $position = 0;
            $addedUrls = [];

            // Ajouter les images par URL soumises via le formulaire
            $imageUrls = $request->request->all('image_urls') ?? [];
            foreach ($imageUrls as $url) {
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $addedUrls)) {
                    $image = new BrickImage();
                    $image->setUrl($url);
                    $image->setPosition($position++);
                    $image->setSource('URL');
                    $image->setBrickSet($set);
                    $this->em->persist($image);
                    $addedUrls[] = $url;
                }
            }

            // Ajouter les images de la session (API) si pas déjà ajoutées
            $sessionImages = $session->get('brick_prefill_images', []);
            foreach ($sessionImages as $imgData) {
                if (!empty($imgData['url']) && !in_array($imgData['url'], $addedUrls)) {
                    $image = new BrickImage();
                    $image->setUrl($imgData['url']);
                    $image->setPosition($position++);
                    $image->setSource($imgData['source'] ?? 'API');
                    $image->setBrickSet($set);
                    $this->em->persist($image);
                    $addedUrls[] = $imgData['url'];
                }
            }

            // Ajouter les images uploadées
            $uploadedFiles = $request->files->get('uploaded_images', []);
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/brick';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            foreach ($uploadedFiles as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                    $file->move($uploadDir, $newFilename);

                    $image = new BrickImage();
                    $image->setFilename($newFilename);
                    $image->setPosition($position++);
                    $image->setSource('Upload');
                    $image->setBrickSet($set);
                    $this->em->persist($image);
                }
            }

            // Lier l'utilisateur au set si demandé
            $addToCollection = $request->request->get('add_to_collection', '1');
            if ($addToCollection === '1') {
                $user = $this->getUser();
                $lien = new LienUserBrickSet();
                $lien->setUser($user);
                $lien->setBrickSet($set);
                
                $dateAchat = $request->request->get('date_achat');
                if ($dateAchat) {
                    $lien->setDateAchat(new \DateTime($dateAchat));
                } else {
                    $lien->setDateAchat(new \DateTime());
                }
                
                $prixAchat = $request->request->get('prix_achat');
                if ($prixAchat) {
                    $lien->setPrixAchat((float) $prixAchat);
                }
                
                $lien->setCommentaire($request->request->get('commentaire_lien'));
                
                $this->em->persist($lien);
            }

            $session->remove('brick_prefill_images');
            $this->em->flush();

            $this->addFlash('success', 'Set créé avec succès' . ($addToCollection === '1' ? ' et ajouté à votre collection' : ''));
            return $this->redirectToRoute('brick_detail', ['id' => $set->getId(), 'from' => 'list']);
        }

        return $this->render('brick/form.html.twig', [
            'form' => $form->createView(),
            'set' => null,
            'isEdit' => false,
            'prefillImages' => $prefillImages,
            'duplicateWarning' => $duplicateWarning,
        ]);
    }

    #[Route('/set/{id}/modifier', name: 'brick_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, BrickSetRepository $setRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        $form = $this->createForm(BrickSetType::class, $set);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $set->setUpdatedAt(new \DateTime());
            $this->em->flush();

            $this->addFlash('success', 'Set modifié avec succès');
            return $this->redirectToRoute('brick_detail', ['id' => $set->getId(), 'from' => 'list']);
        }

        return $this->render('brick/form.html.twig', [
            'form' => $form->createView(),
            'set' => $set,
            'isEdit' => true,
            'prefillImages' => [],
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/set/{id}/supprimer', name: 'brick_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, BrickSetRepository $setRepo, BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $set->getId(), $request->request->get('_token'))) {
            // Supprimer les images uploadées
            foreach ($set->getImages() as $image) {
                if ($image->getFilename()) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/brick/' . $image->getFilename();
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            $this->em->remove($set);
            $this->em->flush();
            
            // Nettoyer les collections et marques orphelines
            $this->cleanOrphanEntities($collectionRepo, $marqueRepo);
            
            $this->addFlash('success', 'Set supprimé');
        }

        return $this->redirectToRoute('brick_list');
    }

    #[Route('/recherche-reference', name: 'brick_search_reference')]
    public function searchByReference(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        return $this->render('brick/search_reference.html.twig', [
            'rebrickableConfigured' => $this->brickApi->isRebrickableConfigured(),
            'bricksetConfigured' => $this->brickApi->isBricksetConfigured(),
        ]);
    }

    #[Route('/api/search', name: 'brick_api_search', methods: ['GET'])]
    public function apiSearch(Request $request, BrickSetRepository $setRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $reference = $request->query->get('reference', '');

        if (empty($reference)) {
            return $this->json(['error' => 'Référence requise'], 400);
        }

        if (!$this->brickApi->isConfigured()) {
            return $this->json(['error' => 'Aucune API configurée'], 500);
        }

        // Vérifier si existe déjà en base (avec et sans suffixe -1)
        $refWithSuffix = preg_match('/-\d+$/', $reference) ? $reference : $reference . '-1';
        $refWithoutSuffix = preg_replace('/-\d+$/', '', $reference);
        
        $existingSet = $setRepo->findByReference($reference) 
            ?? $setRepo->findByReference($refWithSuffix)
            ?? $setRepo->findByReference($refWithoutSuffix);
        
        // Si le set existe, retourner immédiatement sans appeler l'API
        if ($existingSet) {
            return $this->json([
                'success' => true,
                'exists' => true,
                'duplicate' => [
                    'id' => $existingSet->getId(),
                    'nom' => $existingSet->getNom(),
                    'reference' => $existingSet->getReference(),
                ],
            ]);
        }

        // Rechercher via les APIs seulement si le set n'existe pas
        $result = $this->brickApi->searchSet($reference);

        if ($result) {
            return $this->json([
                'success' => true,
                'set' => $result,
                'images' => $result['images'] ?? [],
            ]);
        }

        // Sinon, rechercher par mot-clé
        $results = $this->brickApi->searchSets($reference, 10);

        return $this->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    #[Route('/api/store-images', name: 'brick_api_store_images', methods: ['POST'])]
    public function apiStoreImages(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $images = $data['images'] ?? [];

        $session = $request->getSession();
        $session->set('brick_prefill_images', $images);

        return $this->json(['success' => true, 'count' => count($images)]);
    }

    #[Route('/api/remove-pending-image', name: 'brick_api_remove_pending_image', methods: ['POST'])]
    public function apiRemovePendingImage(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $indexToRemove = $data['index'] ?? null;

        $session = $request->getSession();
        $images = $session->get('brick_prefill_images', []);

        if ($indexToRemove !== null && isset($images[$indexToRemove])) {
            array_splice($images, $indexToRemove, 1);
            $session->set('brick_prefill_images', $images);
        }

        return $this->json(['success' => true, 'count' => count($images), 'images' => $images]);
    }

    #[Route('/api/add-pending-image', name: 'brick_api_add_pending_image', methods: ['POST'])]
    public function apiAddPendingImage(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? null;

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['error' => 'URL invalide'], 400);
        }

        $session = $request->getSession();
        $images = $session->get('brick_prefill_images', []);
        $images[] = ['url' => $url, 'source' => 'Manuel'];
        $session->set('brick_prefill_images', $images);

        return $this->json(['success' => true, 'count' => count($images), 'images' => $images]);
    }

    #[Route('/set/{id}/images', name: 'brick_images', requirements: ['id' => '\d+'])]
    public function manageImages(int $id, Request $request, BrickSetRepository $setRepo, BrickImageRepository $imageRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        if ($request->isMethod('POST')) {
            $files = $request->files->get('images', []);
            $urls = $request->request->all('image_urls') ?? [];

            $maxPosition = $imageRepo->getMaxPosition($id);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/brick';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $file->move($uploadDir, $newFilename);

                    $image = new BrickImage();
                    $image->setFilename($newFilename);
                    $image->setPosition(++$maxPosition);
                    $image->setSource('Upload');
                    $image->setBrickSet($set);
                    $this->em->persist($image);
                }
            }

            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $image = new BrickImage();
                    $image->setUrl($url);
                    $image->setPosition(++$maxPosition);
                    $image->setSource('URL');
                    $image->setBrickSet($set);
                    $this->em->persist($image);
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Images ajoutées avec succès');

            return $this->redirectToRoute('brick_images', ['id' => $id]);
        }

        return $this->render('brick/images.html.twig', [
            'set' => $set,
        ]);
    }

    #[Route('/set/{id}/images/reorder', name: 'brick_images_reorder', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reorderImages(int $id, Request $request, BrickSetRepository $setRepo, BrickImageRepository $imageRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $set = $setRepo->find($id);
        if (!$set) {
            return $this->json(['error' => 'Set non trouvé'], 404);
        }

        $order = $request->request->all('order') ?? [];

        foreach ($order as $position => $imageId) {
            $image = $imageRepo->find($imageId);
            if ($image && $image->getBrickSet()->getId() === $id) {
                $image->setPosition($position);
            }
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/image/{id}/supprimer', name: 'brick_image_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteImage(int $id, BrickImageRepository $imageRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $image = $imageRepo->find($id);
        if (!$image) {
            return $this->json(['error' => 'Image non trouvée'], 404);
        }

        $setId = $image->getBrickSet()->getId();

        if ($image->getFilename()) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/brick/' . $image->getFilename();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $this->em->remove($image);
        $this->em->flush();

        return $this->json(['success' => true, 'setId' => $setId]);
    }

    #[Route('/set/{id}/ajouter-proprietaire', name: 'brick_add_owner', requirements: ['id' => '\d+'])]
    public function addOwner(int $id, Request $request, BrickSetRepository $setRepo, LienUserBrickSetRepository $lienRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('brick');

        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        $user = $this->getUser();

        if ($lienRepo->userOwnsSet($user->getId(), $id)) {
            $this->addFlash('warning', 'Vous possédez déjà ce set');
            return $this->redirectToRoute('brick_detail', ['id' => $id]);
        }

        if ($request->isMethod('POST')) {
            $lien = new LienUserBrickSet();
            $lien->setUser($user);
            $lien->setBrickSet($set);

            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }

            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            }

            $lien->setCommentaire($request->request->get('commentaire'));

            $this->em->persist($lien);
            $this->em->flush();

            $this->addFlash('success', 'Set ajouté à votre collection');
            return $this->redirectToRoute('brick_detail', ['id' => $id]);
        }

        return $this->render('brick/add_owner.html.twig', [
            'set' => $set,
        ]);
    }

    #[Route('/set/{id}/modifier-proprietaire/{lienId}', name: 'brick_edit_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'])]
    public function editOwner(int $id, int $lienId, Request $request, BrickSetRepository $setRepo, LienUserBrickSetRepository $lienRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $set = $setRepo->find($id);
        if (!$set) {
            throw $this->createNotFoundException('Set non trouvé');
        }

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getBrickSet()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        if ($request->isMethod('POST')) {
            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            } else {
                $lien->setDateAchat(null);
            }

            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            } else {
                $lien->setPrixAchat(null);
            }

            $lien->setCommentaire($request->request->get('commentaire'));

            $this->em->flush();

            $this->addFlash('success', 'Informations mises à jour');
            return $this->redirectToRoute('brick_detail', ['id' => $id]);
        }

        return $this->render('brick/edit_owner.html.twig', [
            'set' => $set,
            'lien' => $lien,
        ]);
    }

    #[Route('/set/{id}/retirer-proprietaire/{lienId}', name: 'brick_remove_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'], methods: ['POST'])]
    public function removeOwner(int $id, int $lienId, Request $request, BrickSetRepository $setRepo, LienUserBrickSetRepository $lienRepo, BrickCollectionRepository $collectionRepo, BrickMarqueRepository $marqueRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getBrickSet()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        $set = $lien->getBrickSet();
        $this->em->remove($lien);
        $this->em->flush();

        // Vérifier s'il reste des propriétaires
        $remainingOwners = $lienRepo->count(['brickSet' => $set]);
        
        if ($remainingOwners === 0) {
            // Supprimer les images uploadées
            foreach ($set->getImages() as $image) {
                if ($image->getFilename()) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/brick/' . $image->getFilename();
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            // Supprimer le set (cascade supprime les images et liens)
            $this->em->remove($set);
            $this->em->flush();
            
            // Nettoyer les collections et marques orphelines
            $this->cleanOrphanEntities($collectionRepo, $marqueRepo);
            
            $this->addFlash('info', 'Set supprimé car plus aucun propriétaire');
            return $this->redirectToRoute('brick_list');
        }

        $this->addFlash('success', 'Propriétaire retiré');
        return $this->redirectToRoute('brick_detail', ['id' => $id]);
    }

    #[Route('/collections', name: 'brick_collections')]
    public function collections(BrickCollectionRepository $collectionRepo): Response
    {
        return $this->render('brick/collections.html.twig', [
            'collections' => $collectionRepo->findAllOrdered(),
        ]);
    }

    #[Route('/collection/nouvelle', name: 'brick_collection_new')]
    public function newCollection(Request $request, BrickCollectionRepository $collectionRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $collection = new BrickCollection();
        $form = $this->createForm(BrickCollectionType::class, $collection);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si une collection avec ce nom existe déjà
            $existing = $collectionRepo->findOneBy(['nom' => $collection->getNom()]);
            if ($existing) {
                $this->addFlash('warning', 'Cette collection existe déjà');
                return $this->redirectToRoute('brick_collections');
            }
            
            $this->em->persist($collection);
            $this->em->flush();

            $this->addFlash('success', 'Collection créée avec succès');
            return $this->redirectToRoute('brick_collections');
        }

        return $this->render('brick/collection_form.html.twig', [
            'form' => $form->createView(),
            'collection' => null,
            'isEdit' => false,
        ]);
    }

    #[Route('/api/collection/create', name: 'brick_api_collection_create', methods: ['POST'])]
    public function apiCreateCollection(Request $request, BrickCollectionRepository $collectionRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $nom = $request->request->get('nom');
        if (empty($nom)) {
            return $this->json(['error' => 'Nom requis'], 400);
        }

        // Vérifier si existe déjà
        $existing = $collectionRepo->findOneBy(['nom' => $nom]);
        if ($existing) {
            return $this->json([
                'success' => true,
                'exists' => true,
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
            ]);
        }

        $collection = new BrickCollection();
        $collection->setNom($nom);

        $this->em->persist($collection);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'id' => $collection->getId(),
            'nom' => $collection->getNom(),
        ]);
    }

    #[Route('/marques', name: 'brick_marques')]
    public function marques(BrickMarqueRepository $marqueRepo): Response
    {
        return $this->render('brick/marques.html.twig', [
            'marques' => $marqueRepo->findAllOrdered(),
        ]);
    }

    #[Route('/marque/nouvelle', name: 'brick_marque_new')]
    public function newMarque(Request $request, BrickMarqueRepository $marqueRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $marque = new BrickMarque();
        $form = $this->createForm(BrickMarqueType::class, $marque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si une marque avec ce nom existe déjà
            $existing = $marqueRepo->findOneBy(['nom' => $marque->getNom()]);
            if ($existing) {
                $this->addFlash('warning', 'Cette marque existe déjà');
                return $this->redirectToRoute('brick_marques');
            }
            
            $this->em->persist($marque);
            $this->em->flush();

            $this->addFlash('success', 'Marque créée avec succès');
            return $this->redirectToRoute('brick_marques');
        }

        return $this->render('brick/marque_form.html.twig', [
            'form' => $form->createView(),
            'marque' => null,
            'isEdit' => false,
        ]);
    }

    #[Route('/api/marque/create', name: 'brick_api_marque_create', methods: ['POST'])]
    public function apiCreateMarque(Request $request, BrickMarqueRepository $marqueRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $nom = $request->request->get('nom');
        if (empty($nom)) {
            return $this->json(['error' => 'Nom requis'], 400);
        }

        // Vérifier si existe déjà
        $existing = $marqueRepo->findOneBy(['nom' => $nom]);
        if ($existing) {
            return $this->json([
                'success' => true,
                'exists' => true,
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
            ]);
        }

        $marque = new BrickMarque();
        $marque->setNom($nom);

        $this->em->persist($marque);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'id' => $marque->getId(),
            'nom' => $marque->getNom(),
        ]);
    }
}
