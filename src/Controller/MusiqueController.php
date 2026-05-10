<?php

namespace App\Controller;

use App\Entity\Musique;
use App\Entity\MusiqueUserCollection;
use App\Form\MusiqueType;
use App\Repository\MusiqueRepository;
use App\Repository\MusiqueUserCollectionRepository;
use App\Repository\UserRepository;
use App\Service\MusiqueApiService;
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

#[Route('/musique')]
class MusiqueController extends AbstractController
{
    private EntityManagerInterface $em;
    private MusiqueApiService $musiqueApi;
    private SectionPermissionService $permissionService;

    public function __construct(
        EntityManagerInterface $em,
        MusiqueApiService $musiqueApi,
        SectionPermissionService $permissionService,
    ) {
        $this->em = $em;
        $this->musiqueApi = $musiqueApi;
        $this->permissionService = $permissionService;
    }

    #[Route('/', name: 'musique_index')]
    public function index(MusiqueRepository $musiqueRepo, MusiqueUserCollectionRepository $lienRepo, UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('musique')) {
                $user->musiqueCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('musique/index.html.twig', [
            'totalMusiques' => $musiqueRepo->countAll(),
            'users' => $usersWithCount,
            'years' => $musiqueRepo->getDistinctYears(),
        ]);
    }

    #[Route('/liste', name: 'musique_list')]
    public function list(Request $request, MusiqueRepository $musiqueRepo, PaginatorInterface $paginator, UserRepository $userRepo, MusiqueUserCollectionRepository $lienRepo): Response
    {
        $search = $request->query->get('search');
        $format = $request->query->get('format');
        $user_id = $request->query->get('user');
        $year = $request->query->get('year');

        $musiques = $musiqueRepo->findBySearch($search, $format, $user_id, $year);

        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('musique')) {
                $user->musiqueCount = $count;
                $usersWithCount[] = $user;
            }
        }

        $pagination = $paginator->paginate(
            $musiques,
            $request->query->getInt('page', 1),
            24
        );

        return $this->render('musique/list.html.twig', [
            'musiques' => $pagination,
            'search' => $search,
            'format' => $format,
            'user' => $user_id,
            'year' => $year,
            'users' => $usersWithCount,
            'years' => $musiqueRepo->getDistinctYears(),
        ]);
    }

    #[Route('/utilisateurs', name: 'musique_users')]
    public function users(UserRepository $userRepo, MusiqueUserCollectionRepository $lienRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('musique')) {
                $user->musiqueCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('musique/users.html.twig', [
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/utilisateur/{id}', name: 'musique_user', requirements: ['id' => '\d+'])]
    public function userCollection(int $id, UserRepository $userRepo, MusiqueUserCollectionRepository $lienRepo): Response
    {
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $liens = $lienRepo->findByUser($user);

        return $this->render('musique/user_collection.html.twig', [
            'user' => $user,
            'liens' => $liens,
        ]);
    }

    #[Route('/ajouter', name: 'musique_ajouter')]
    public function ajouter(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        return $this->render('musique/ajouter_choix.html.twig', [
            'apiConfigured' => $this->musiqueApi->isConfigured(),
        ]);
    }

    #[Route('/nouveau', name: 'musique_new')]
    public function new(Request $request, MusiqueRepository $musiqueRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        $session = $request->getSession();
        $musique = new Musique();
        $prefillImages = [];
        $duplicateWarning = null;

        if (!$request->query->get('prefill')) {
            $session->remove('musique_prefill_images');
        }

        if ($request->query->get('prefill')) {
            $musique->setTitre($request->query->get('titre', ''));
            if ($request->query->get('artiste')) {
                $musique->setArtiste($request->query->get('artiste'));
            }
            if ($request->query->get('annee')) {
                $musique->setAnnee((int) $request->query->get('annee'));
            }
            if ($request->query->get('label')) {
                $musique->setLabel($request->query->get('label'));
            }
            if ($request->query->get('genre')) {
                $musique->setGenre($request->query->get('genre'));
            }
            if ($request->query->get('type')) {
                $musique->setType($request->query->get('type'));
            }
            if ($request->query->get('format')) {
                $musique->setFormat($request->query->get('format'));
            }
            if ($request->query->get('description')) {
                $musique->setDescription($request->query->get('description'));
            }
            if ($request->query->get('cover')) {
                $musique->setCoverUrl($request->query->get('cover'));
            }
            if ($request->query->get('externalId')) {
                $musique->setExternalId($request->query->get('externalId'));
            }
            if ($request->query->get('tracklist')) {
                $musique->setTracklist($request->query->get('tracklist'));
            }

            $existing = null;
            $titre = trim($musique->getTitre() ?? '');
            $artiste = trim($musique->getArtiste() ?? '');
            $externalId = $request->query->get('externalId');

            if ($externalId) {
                $existing = $musiqueRepo->findOneBy(['externalId' => $externalId]);
            }
            if (!$existing && !empty($titre) && !empty($artiste)) {
                $existing = $musiqueRepo->createQueryBuilder('m')
                    ->where('LOWER(m.titre) = LOWER(:titre)')
                    ->andWhere('LOWER(m.artiste) = LOWER(:artiste)')
                    ->setParameter('titre', $titre)
                    ->setParameter('artiste', $artiste)
                    ->getQuery()
                    ->getOneOrNullResult();
            }

            if ($existing) {
                $duplicateWarning = [
                    'message' => 'Cet album existe déjà dans la base !',
                    'musique' => $existing,
                ];
            }

            $prefillImages = $session->get('musique_prefill_images', []);
        }

        $form = $this->createForm(MusiqueType::class, $musique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $musiqueRepo->createQueryBuilder('m')
                ->where('LOWER(m.titre) = LOWER(:titre)')
                ->setParameter('titre', trim($musique->getTitre()));

            if ($musique->getArtiste()) {
                $qb->andWhere('LOWER(m.artiste) = LOWER(:artiste)')
                   ->setParameter('artiste', trim($musique->getArtiste()));
            }

            $existing = $qb->getQuery()->getOneOrNullResult();
            if ($existing && $existing->getId() !== $musique->getId()) {
                $this->addFlash('danger', 'Cet album existe déjà dans la base !');
                return $this->render('musique/form.html.twig', [
                    'form' => $form->createView(),
                    'musique' => null,
                    'isEdit' => false,
                    'prefillImages' => $prefillImages,
                    'duplicateWarning' => [
                        'message' => 'Cet album existe déjà !',
                        'musique' => $existing,
                    ],
                ]);
            }

            // Upload cover
            $coverUpload = $request->files->get('cover_upload');
            if ($coverUpload) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/musique';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $extension = $coverUpload->guessExtension() ?? 'jpg';
                $filename = 'musique_' . uniqid() . '.' . $extension;
                try {
                    $coverUpload->move($uploadDir, $filename);
                    $musique->setCoverUrl('/uploads/musique/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur upload pochette: ' . $e->getMessage());
                }
            }

            $this->em->persist($musique);

            // Ajouter à la collection
            $addToCollection = $request->request->get('add_to_collection', '1');
            if ($addToCollection === '1') {
                $lien = new MusiqueUserCollection();
                $lien->setUser($this->getUser());
                $lien->setMusique($musique);

                $dateAchat = $request->request->get('date_achat');
                if ($dateAchat) {
                    $lien->setDateAchat(new \DateTime($dateAchat));
                }

                $prixAchat = $request->request->get('prix_achat');
                if ($prixAchat) {
                    $lien->setPrixAchat((float) $prixAchat);
                }

                $lien->setCommentaire($request->request->get('commentaire_lien'));

                $imagePerso = $request->files->get('image_perso');
                $imagePersoUrl = $request->request->get('image_perso_url');

                if ($imagePerso instanceof UploadedFile && $imagePerso->isValid()) {
                    $userUploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/musique/user';
                    if (!is_dir($userUploadDir)) {
                        mkdir($userUploadDir, 0777, true);
                    }
                    $originalFilename = pathinfo($imagePerso->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imagePerso->guessExtension();
                    $imagePerso->move($userUploadDir, $newFilename);
                    $lien->setImagePerso($newFilename);
                } elseif ($imagePersoUrl && filter_var($imagePersoUrl, FILTER_VALIDATE_URL)) {
                    $lien->setImagePerso($imagePersoUrl);
                }

                $this->em->persist($lien);
            }

            $session->remove('musique_prefill_images');
            $this->em->flush();

            $this->addFlash('success', 'Album créé avec succès' . ($addToCollection === '1' ? ' et ajouté à votre collection' : ''));
            return $this->redirectToRoute('musique_detail', ['id' => $musique->getId()]);
        }

        return $this->render('musique/form.html.twig', [
            'form' => $form->createView(),
            'musique' => $musique,
            'isEdit' => false,
            'prefillImages' => $prefillImages,
            'duplicateWarning' => $duplicateWarning,
        ]);
    }

    #[Route('/{id}', name: 'musique_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, MusiqueRepository $musiqueRepo, MusiqueUserCollectionRepository $lienRepo): Response
    {
        $musique = $musiqueRepo->find($id);
        if (!$musique) {
            throw $this->createNotFoundException('Album non trouvé');
        }

        $owners = $lienRepo->findByMusique($musique);
        $userLinks = [];
        if ($this->getUser()) {
            $userLinks = $lienRepo->findBy(['user' => $this->getUser(), 'musique' => $musique]);
        }

        return $this->render('musique/detail.html.twig', [
            'musique' => $musique,
            'owners' => $owners,
            'userLinks' => $userLinks,
        ]);
    }

    #[Route('/{id}/modifier', name: 'musique_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, MusiqueRepository $musiqueRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        $musique = $musiqueRepo->find($id);
        if (!$musique) {
            throw $this->createNotFoundException('Album non trouvé');
        }

        $form = $this->createForm(MusiqueType::class, $musique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverUpload = $request->files->get('cover_upload');
            if ($coverUpload) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/musique';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $oldCover = $musique->getCoverUrl();
                if ($oldCover && str_starts_with($oldCover, '/uploads/musique/')) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $oldCover;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $extension = $coverUpload->guessExtension() ?? 'jpg';
                $filename = 'musique_' . uniqid() . '.' . $extension;
                try {
                    $coverUpload->move($uploadDir, $filename);
                    $musique->setCoverUrl('/uploads/musique/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur upload pochette: ' . $e->getMessage());
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Album modifié avec succès');
            return $this->redirectToRoute('musique_detail', ['id' => $musique->getId()]);
        }

        return $this->render('musique/form.html.twig', [
            'form' => $form->createView(),
            'musique' => $musique,
            'isEdit' => true,
            'prefillImages' => [],
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'musique_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, MusiqueRepository $musiqueRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        $musique = $musiqueRepo->find($id);
        if (!$musique) {
            throw $this->createNotFoundException('Album non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $musique->getId(), $request->request->get('_token'))) {
            $this->em->remove($musique);
            $this->em->flush();
            $this->addFlash('success', 'Album supprimé');
        }

        return $this->redirectToRoute('musique_list');
    }

    #[Route('/recherche-api', name: 'musique_search_api')]
    public function searchApi(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        return $this->render('musique/search_api.html.twig', [
            'apiConfigured' => $this->musiqueApi->isConfigured(),
        ]);
    }

    #[Route('/api/search', name: 'musique_api_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $query = $request->query->get('q', '');
        $barcode = $request->query->get('barcode', '');
        $format = $request->query->get('format');

        if (empty($query) && empty($barcode)) {
            return $this->json(['error' => 'Recherche requise'], 400);
        }

        if (!$this->musiqueApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        // Recherche par code-barres si fourni
        if (!empty($barcode)) {
            $results = $this->musiqueApi->searchByBarcode($barcode);
        } else {
            $results = $this->musiqueApi->search($query, $format, null, 20);
        }

        return $this->json([
            'success' => true,
            'results' => $results,
            'searchType' => !empty($barcode) ? 'barcode' : 'text',
        ]);
    }

    #[Route('/api/master/{masterId}/releases', name: 'musique_api_master_releases', requirements: ['masterId' => '\d+'], methods: ['GET'])]
    public function apiMasterReleases(string $masterId): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->musiqueApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        $releases = $this->musiqueApi->getMasterReleases($masterId, 20);

        return $this->json([
            'success' => true,
            'releases' => $releases,
        ]);
    }

    #[Route('/api/details/{releaseId}', name: 'musique_api_details', requirements: ['releaseId' => '\d+'], methods: ['GET'])]
    public function apiDetails(string $releaseId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->musiqueApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        $details = $this->musiqueApi->getDetails($releaseId);

        if (!$details) {
            return $this->json(['error' => 'Album non trouvé'], 404);
        }

        $session = $request->getSession();
        $images = [];
        if (!empty($details['cover'])) {
            $images[] = ['url' => $details['cover'], 'source' => 'Discogs'];
        }
        $session->set('musique_prefill_images', $images);

        return $this->json([
            'success' => true,
            'musique' => $details,
        ]);
    }

    #[Route('/api/master/{masterId}', name: 'musique_api_master_details', requirements: ['masterId' => '\d+'], methods: ['GET'])]
    public function apiMasterDetails(string $masterId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->musiqueApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        // Essayer d'abord comme master
        $details = $this->musiqueApi->getMasterDetails($masterId);

        // Si ça échoue, essayer comme release normale (fallback)
        if (!$details) {
            $details = $this->musiqueApi->getDetails($masterId);
        }

        if (!$details) {
            return $this->json(['error' => 'Album non trouvé'], 404);
        }

        $session = $request->getSession();
        $images = [];
        if (!empty($details['cover'])) {
            $images[] = ['url' => $details['cover'], 'source' => 'Discogs'];
        }
        $session->set('musique_prefill_images', $images);

        return $this->json([
            'success' => true,
            'musique' => $details,
        ]);
    }

    #[Route('/{id}/ajouter-collection', name: 'musique_add_owner', requirements: ['id' => '\d+'])]
    public function addOwner(int $id, Request $request, MusiqueRepository $musiqueRepo, MusiqueUserCollectionRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('musique');

        $musique = $musiqueRepo->find($id);
        if (!$musique) {
            throw $this->createNotFoundException('Album non trouvé');
        }

        if ($request->isMethod('POST')) {
            $lien = new MusiqueUserCollection();
            $lien->setUser($this->getUser());
            $lien->setMusique($musique);

            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }

            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            }

            $lien->setCommentaire($request->request->get('commentaire'));

            $imageFile = $request->files->get('image_perso');
            $imageUrl = $request->request->get('image_perso_url');

            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/musique/user';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $newFilename);
                $lien->setImagePerso($newFilename);
            } elseif ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $lien->setImagePerso($imageUrl);
            }

            $this->em->persist($lien);
            $this->em->flush();

            $this->addFlash('success', 'Album ajouté à votre collection');
            return $this->redirectToRoute('musique_detail', ['id' => $id]);
        }

        return $this->render('musique/add_owner.html.twig', [
            'musique' => $musique,
        ]);
    }

    #[Route('/{id}/modifier-collection/{lienId}', name: 'musique_edit_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'])]
    public function editOwner(int $id, int $lienId, Request $request, MusiqueRepository $musiqueRepo, MusiqueUserCollectionRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getMusique()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        if ($lien->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $musique = $lien->getMusique();

        if ($request->isMethod('POST')) {
            $dateAchat = $request->request->get('date_achat');
            if ($dateAchat) {
                $lien->setDateAchat(new \DateTime($dateAchat));
            }

            $prixAchat = $request->request->get('prix_achat');
            if ($prixAchat) {
                $lien->setPrixAchat((float) $prixAchat);
            }

            $lien->setCommentaire($request->request->get('commentaire'));

            $imageFile = $request->files->get('image_perso');
            $imageUrl = $request->request->get('image_perso_url');

            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                if ($lien->getImagePerso() && !filter_var($lien->getImagePerso(), FILTER_VALIDATE_URL)) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/musique/user/' . $lien->getImagePerso();
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/musique/user';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadDir, $newFilename);
                $lien->setImagePerso($newFilename);
            } elseif ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $lien->setImagePerso($imageUrl);
            }

            $this->em->flush();
            $this->addFlash('success', 'Collection mise à jour');
            return $this->redirectToRoute('musique_detail', ['id' => $id]);
        }

        return $this->render('musique/edit_owner.html.twig', [
            'musique' => $musique,
            'lien' => $lien,
        ]);
    }
}
