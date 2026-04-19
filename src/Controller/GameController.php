<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\GameImage;
use App\Entity\LienUserGame;
use App\Form\GameType;
use App\Repository\GameRepository;
use App\Repository\GameImageRepository;
use App\Repository\LienUserGameRepository;
use App\Repository\UserRepository;
use App\Service\GameApiService;
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

#[Route('/games')]
class GameController extends AbstractController
{
    private EntityManagerInterface $em;
    private GameApiService $gameApi;
    private SectionPermissionService $permissionService;

    public function __construct(EntityManagerInterface $em, GameApiService $gameApi, SectionPermissionService $permissionService)
    {
        $this->em = $em;
        $this->gameApi = $gameApi;
        $this->permissionService = $permissionService;
    }

    #[Route('/', name: 'game_index')]
    public function index(GameRepository $gameRepo, LienUserGameRepository $lienRepo, UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            // Afficher si l'utilisateur a du contenu OU s'il a la permission d'enregistrer
            if ($count > 0 || $user->canRegisterInSection('games')) {
                $user->gamesCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('game/index.html.twig', [
            'totalGames' => $gameRepo->countAll(),
            'consoles' => $gameRepo->getDistinctConsoles(),
            'genres' => $gameRepo->getDistinctGenres(),
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/liste', name: 'game_list')]
    public function list(Request $request, GameRepository $gameRepo, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search');
        $console = $request->query->get('console');
        $genre = $request->query->get('genre');

        $games = $gameRepo->findBySearch($search, $console, $genre);

        $pagination = $paginator->paginate(
            $games,
            $request->query->getInt('page', 1),
            24
        );

        return $this->render('game/list.html.twig', [
            'games' => $pagination,
            'search' => $search,
            'console' => $console,
            'genre' => $genre,
            'consoles' => $gameRepo->getDistinctConsoles(),
            'genres' => $gameRepo->getDistinctGenres(),
        ]);
    }

    #[Route('/utilisateurs', name: 'game_users')]
    public function users(UserRepository $userRepo, LienUserGameRepository $lienRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            // Afficher si l'utilisateur a du contenu OU s'il a la permission d'enregistrer
            if ($count > 0 || $user->canRegisterInSection('games')) {
                $user->gamesCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('game/users.html.twig', [
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/nouveau', name: 'game_new')]
    public function new(Request $request, GameRepository $gameRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('games');

        $session = $request->getSession();
        $game = new Game();
        $prefillImages = [];
        $duplicateWarning = null;

        if (!$request->query->get('prefill')) {
            $session->remove('game_prefill_images');
        }

        // Pré-remplissage depuis l'API
        if ($request->query->get('prefill')) {
            $game->setTitre($request->query->get('titre', ''));
            $game->setConsole($request->query->get('console', ''));
            
            if ($request->query->get('annee')) {
                $game->setAnnee((int) $request->query->get('annee'));
            }
            if ($request->query->get('editeur')) {
                $game->setEditeur($request->query->get('editeur'));
            }
            if ($request->query->get('developpeur')) {
                $game->setDeveloppeur($request->query->get('developpeur'));
            }
            if ($request->query->get('genre')) {
                $game->setGenre($request->query->get('genre'));
            }
            if ($request->query->get('classification')) {
                $game->setClassification($request->query->get('classification'));
            }
            if ($request->query->get('description')) {
                $game->setDescription($request->query->get('description'));
            }
            if ($request->query->get('cover')) {
                $game->setCoverUrl($request->query->get('cover'));
            }
            if ($request->query->get('externalId')) {
                $game->setExternalId($request->query->get('externalId'));
            }

            // Vérifier si le jeu existe déjà
            $existing = $gameRepo->findByTitreAndConsole($game->getTitre(), $game->getConsole());
            if ($existing) {
                $duplicateWarning = [
                    'message' => 'Ce jeu existe déjà pour cette plateforme !',
                    'game' => $existing,
                ];
            }

            $prefillImages = $session->get('game_prefill_images', []);
        }

        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier doublon
            $existing = $gameRepo->findByTitreAndConsole($game->getTitre(), $game->getConsole());
            if ($existing) {
                $this->addFlash('danger', 'Ce jeu existe déjà pour cette plateforme !');
                return $this->render('game/form.html.twig', [
                    'form' => $form->createView(),
                    'game' => null,
                    'isEdit' => false,
                    'prefillImages' => $prefillImages,
                    'duplicateWarning' => [
                        'message' => 'Ce jeu existe déjà !',
                        'game' => $existing,
                    ],
                ]);
            }

            $this->em->persist($game);

            $position = 0;
            $addedUrls = [];

            // Images par URL
            $imageUrls = $request->request->all('image_urls') ?? [];
            foreach ($imageUrls as $url) {
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && !in_array($url, $addedUrls)) {
                    $image = new GameImage();
                    $image->setUrl($url);
                    $image->setPosition($position++);
                    $image->setSource('URL');
                    $image->setGame($game);
                    $this->em->persist($image);
                    $addedUrls[] = $url;
                }
            }

            // Images de session (API)
            $sessionImages = $session->get('game_prefill_images', []);
            foreach ($sessionImages as $imgData) {
                if (!empty($imgData['url']) && !in_array($imgData['url'], $addedUrls)) {
                    $image = new GameImage();
                    $image->setUrl($imgData['url']);
                    $image->setPosition($position++);
                    $image->setSource($imgData['source'] ?? 'API');
                    $image->setGame($game);
                    $this->em->persist($image);
                    $addedUrls[] = $imgData['url'];
                }
            }

            // Images uploadées
            $uploadedFiles = $request->files->get('uploaded_images', []);
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/game';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            foreach ($uploadedFiles as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                    $file->move($uploadDir, $newFilename);

                    $image = new GameImage();
                    $image->setFilename($newFilename);
                    $image->setPosition($position++);
                    $image->setSource('Upload');
                    $image->setGame($game);
                    $this->em->persist($image);
                }
            }

            // Ajouter à la collection si demandé
            $addToCollection = $request->request->get('add_to_collection', '1');
            if ($addToCollection === '1') {
                $lien = new LienUserGame();
                $lien->setUser($this->getUser());
                $lien->setGame($game);
                $lien->setTypeEdition($request->request->get('type_edition', 'physique'));
                $lien->setNomEdition($request->request->get('nom_edition'));
                
                $dateAchat = $request->request->get('date_achat');
                if ($dateAchat) {
                    $lien->setDateAchat(new \DateTime($dateAchat));
                }
                
                $prixAchat = $request->request->get('prix_achat');
                if ($prixAchat) {
                    $lien->setPrixAchat((float) $prixAchat);
                }
                
                if ($request->request->get('type_edition') === 'numerique') {
                    $lien->setStore($request->request->get('store'));
                }
                
                $lien->setCommentaire($request->request->get('commentaire_lien'));
                
                $this->em->persist($lien);
            }

            $session->remove('game_prefill_images');
            $this->em->flush();

            $this->addFlash('success', 'Jeu créé avec succès' . ($addToCollection === '1' ? ' et ajouté à votre collection' : ''));
            return $this->redirectToRoute('game_detail', ['id' => $game->getId()]);
        }

        return $this->render('game/form.html.twig', [
            'form' => $form->createView(),
            'game' => $game,
            'isEdit' => false,
            'prefillImages' => $prefillImages,
            'duplicateWarning' => $duplicateWarning,
        ]);
    }

    #[Route('/jeu/{id}', name: 'game_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, GameRepository $gameRepo, LienUserGameRepository $lienRepo): Response
    {
        $game = $gameRepo->find($id);
        if (!$game) {
            throw $this->createNotFoundException('Jeu non trouvé');
        }

        $owners = $lienRepo->findByGame($game);
        $userHasGame = false;
        $userLink = null;
        
        if ($this->getUser()) {
            $userLink = $lienRepo->findUserGameLink($this->getUser(), $game);
            $userHasGame = $userLink !== null;
        }

        return $this->render('game/detail.html.twig', [
            'game' => $game,
            'owners' => $owners,
            'userHasGame' => $userHasGame,
            'userLink' => $userLink,
        ]);
    }

    #[Route('/jeu/{id}/modifier', name: 'game_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, GameRepository $gameRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('games');

        $game = $gameRepo->find($id);
        if (!$game) {
            throw $this->createNotFoundException('Jeu non trouvé');
        }

        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Jeu modifié avec succès');
            return $this->redirectToRoute('game_detail', ['id' => $game->getId()]);
        }

        return $this->render('game/form.html.twig', [
            'form' => $form->createView(),
            'game' => $game,
            'isEdit' => true,
            'prefillImages' => [],
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/jeu/{id}/supprimer', name: 'game_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, GameRepository $gameRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('games');

        $game = $gameRepo->find($id);
        if (!$game) {
            throw $this->createNotFoundException('Jeu non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $game->getId(), $request->request->get('_token'))) {
            // Supprimer les images uploadées
            foreach ($game->getImages() as $image) {
                if ($image->getFilename()) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/game/' . $image->getFilename();
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            $this->em->remove($game);
            $this->em->flush();
            $this->addFlash('success', 'Jeu supprimé');
        }

        return $this->redirectToRoute('game_list');
    }

    #[Route('/recherche-api', name: 'game_search_api')]
    public function searchApi(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('games');

        return $this->render('game/search_api.html.twig', [
            'apiConfigured' => $this->gameApi->isConfigured(),
        ]);
    }

    #[Route('/api/search', name: 'game_api_search', methods: ['GET'])]
    public function apiSearch(Request $request, GameRepository $gameRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $query = $request->query->get('q', '');

        if (empty($query)) {
            return $this->json(['error' => 'Recherche requise'], 400);
        }

        if (!$this->gameApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        $results = $this->gameApi->searchGames($query, 10);

        return $this->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    #[Route('/api/details/{gameId}', name: 'game_api_details', requirements: ['gameId' => '\d+'], methods: ['GET'])]
    public function apiDetails(int $gameId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->gameApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        try {
            $details = $this->gameApi->getGameDetails($gameId);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Exception: ' . $e->getMessage()], 500);
        }

        if (!$details) {
            return $this->json(['error' => 'Jeu non trouvé (details null)'], 404);
        }

        // Stocker les screenshots en session
        $session = $request->getSession();
        $images = [];
        if (!empty($details['cover'])) {
            $images[] = ['url' => $details['cover'], 'source' => 'API'];
        }
        foreach ($details['screenshots'] ?? [] as $screenshot) {
            $images[] = ['url' => $screenshot, 'source' => 'API'];
        }
        $session->set('game_prefill_images', $images);

        return $this->json([
            'success' => true,
            'game' => $details,
        ]);
    }

    #[Route('/jeu/{id}/ajouter-collection', name: 'game_add_owner', requirements: ['id' => '\d+'])]
    public function addOwner(int $id, Request $request, GameRepository $gameRepo, LienUserGameRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('games');

        $game = $gameRepo->find($id);
        if (!$game) {
            throw $this->createNotFoundException('Jeu non trouvé');
        }

        if ($request->isMethod('POST')) {
            $lien = new LienUserGame();
            $lien->setUser($this->getUser());
            $lien->setGame($game);
            $lien->setTypeEdition($request->request->get('type_edition', 'physique'));
            $lien->setNomEdition($request->request->get('nom_edition'));
            
            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }
            
            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            }
            
            if ($request->request->get('type_edition') === 'numerique') {
                $lien->setStore($request->request->get('store'));
            }
            
            $lien->setCommentaire($request->request->get('commentaire'));

            // Image personnalisée
            $imageFile = $request->files->get('image_perso');
            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/game/user';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $newFilename);
                $lien->setImagePerso($newFilename);
            }
            
            $this->em->persist($lien);
            $this->em->flush();

            $this->addFlash('success', 'Jeu ajouté à votre collection');
            return $this->redirectToRoute('game_detail', ['id' => $id]);
        }

        return $this->render('game/add_owner.html.twig', [
            'game' => $game,
        ]);
    }

    #[Route('/jeu/{id}/modifier-collection/{lienId}', name: 'game_edit_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'])]
    public function editOwner(int $id, int $lienId, Request $request, GameRepository $gameRepo, LienUserGameRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getGame()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        // Vérifier que c'est bien l'utilisateur propriétaire
        if ($lien->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $game = $lien->getGame();

        if ($request->isMethod('POST')) {
            $lien->setTypeEdition($request->request->get('type_edition', 'physique'));
            $lien->setNomEdition($request->request->get('nom_edition'));
            
            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }
            
            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            }
            
            if ($request->request->get('type_edition') === 'numerique') {
                $lien->setStore($request->request->get('store'));
            } else {
                $lien->setStore(null);
            }
            
            $lien->setCommentaire($request->request->get('commentaire'));

            // Image personnalisée
            $imageFile = $request->files->get('image_perso');
            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                // Supprimer l'ancienne image si existe
                if ($lien->getImagePerso() && !filter_var($lien->getImagePerso(), FILTER_VALIDATE_URL)) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/game/user/' . $lien->getImagePerso();
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/game/user';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $newFilename);
                $lien->setImagePerso($newFilename);
            }
            
            $this->em->flush();

            $this->addFlash('success', 'Informations mises à jour');
            return $this->redirectToRoute('game_detail', ['id' => $id]);
        }

        return $this->render('game/edit_owner.html.twig', [
            'game' => $game,
            'lien' => $lien,
        ]);
    }

    #[Route('/jeu/{id}/retirer-collection/{lienId}', name: 'game_remove_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'], methods: ['POST'])]
    public function removeOwner(int $id, int $lienId, Request $request, LienUserGameRepository $lienRepo, GameRepository $gameRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getGame()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        $game = $lien->getGame();
        
        // Supprimer l'image personnalisée si existe
        if ($lien->getImagePerso() && !filter_var($lien->getImagePerso(), FILTER_VALIDATE_URL)) {
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/game/user/' . $lien->getImagePerso();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $this->em->remove($lien);
        $this->em->flush();

        // Vérifier s'il reste des propriétaires
        $remainingOwners = $lienRepo->count(['game' => $game]);
        
        if ($remainingOwners === 0) {
            // Supprimer les images du jeu
            foreach ($game->getImages() as $image) {
                if ($image->getFilename()) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/game/' . $image->getFilename();
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            $this->em->remove($game);
            $this->em->flush();
            
            $this->addFlash('info', 'Jeu supprimé car plus aucun propriétaire');
            return $this->redirectToRoute('game_list');
        }

        $this->addFlash('success', 'Jeu retiré de votre collection');
        return $this->redirectToRoute('game_detail', ['id' => $id]);
    }
}
