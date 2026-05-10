<?php

namespace App\Controller;

use App\Entity\Dvd;
use App\Entity\DvdUserCollection;
use App\Form\DvdType;
use App\Repository\DvdRepository;
use App\Repository\DvdUserCollectionRepository;
use App\Repository\UserRepository;
use App\Service\DvdApiService;
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

#[Route('/dvd')]
class DvdController extends AbstractController
{
    private EntityManagerInterface $em;
    private DvdApiService $dvdApi;
    private SectionPermissionService $permissionService;

    public function __construct(
        EntityManagerInterface $em,
        DvdApiService $dvdApi,
        SectionPermissionService $permissionService,
    ) {
        $this->em = $em;
        $this->dvdApi = $dvdApi;
        $this->permissionService = $permissionService;
    }

    #[Route('/', name: 'dvd_index')]
    public function index(DvdRepository $dvdRepo, DvdUserCollectionRepository $lienRepo, UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('dvd')) {
                $user->dvdCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('dvd/index.html.twig', [
            'totalDvds' => $dvdRepo->countAll(),
            'users' => $usersWithCount,
            'years' => $dvdRepo->getDistinctYears(),
        ]);
    }

    #[Route('/liste', name: 'dvd_list')]
    public function list(Request $request, DvdRepository $dvdRepo, PaginatorInterface $paginator, UserRepository $userRepo, DvdUserCollectionRepository $lienRepo): Response
    {
        $search = $request->query->get('search');
        $format = $request->query->get('format');
        $user_id = $request->query->get('user');
        $year = $request->query->get('year');

        $dvds = $dvdRepo->findBySearch($search, $format, $user_id, $year);

        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('dvd')) {
                $user->dvdCount = $count;
                $usersWithCount[] = $user;
            }
        }

        $pagination = $paginator->paginate(
            $dvds,
            $request->query->getInt('page', 1),
            24
        );

        return $this->render('dvd/list.html.twig', [
            'dvds' => $pagination,
            'search' => $search,
            'format' => $format,
            'user' => $user_id,
            'year' => $year,
            'users' => $usersWithCount,
            'years' => $dvdRepo->getDistinctYears(),
        ]);
    }

    #[Route('/utilisateurs', name: 'dvd_users')]
    public function users(UserRepository $userRepo, DvdUserCollectionRepository $lienRepo): Response
    {
        $users = $userRepo->findAll();
        $usersWithCount = [];
        foreach ($users as $user) {
            $count = $lienRepo->countByUser($user);
            if ($count > 0 || $user->canRegisterInSection('dvd')) {
                $user->dvdCount = $count;
                $usersWithCount[] = $user;
            }
        }

        return $this->render('dvd/users.html.twig', [
            'users' => $usersWithCount,
        ]);
    }

    #[Route('/utilisateur/{id}', name: 'dvd_user', requirements: ['id' => '\d+'])]
    public function userCollection(int $id, UserRepository $userRepo, DvdUserCollectionRepository $lienRepo): Response
    {
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $liens = $lienRepo->findByUser($user);

        return $this->render('dvd/user_collection.html.twig', [
            'user' => $user,
            'liens' => $liens,
        ]);
    }

    #[Route('/ajouter', name: 'dvd_ajouter')]
    public function ajouter(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        return $this->render('dvd/ajouter_choix.html.twig', [
            'apiConfigured' => $this->dvdApi->isConfigured(),
        ]);
    }

    #[Route('/nouveau', name: 'dvd_new')]
    public function new(Request $request, DvdRepository $dvdRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        $session = $request->getSession();
        $dvd = new Dvd();
        $prefillImages = [];
        $duplicateWarning = null;

        if (!$request->query->get('prefill')) {
            $session->remove('dvd_prefill_images');
        }

        if ($request->query->get('prefill')) {
            $dvd->setTitre($request->query->get('titre', ''));
            if ($request->query->get('annee')) {
                $dvd->setAnnee((int) $request->query->get('annee'));
            }
            if ($request->query->get('editeur')) {
                $dvd->setEditeur($request->query->get('editeur'));
            }
            if ($request->query->get('type')) {
                $dvd->setType($request->query->get('type'));
            }
            if ($request->query->get('format')) {
                $dvd->setFormat($request->query->get('format'));
            }
            if ($request->query->get('description')) {
                $dvd->setDescription($request->query->get('description'));
            }
            if ($request->query->get('cover')) {
                $dvd->setCoverUrl($request->query->get('cover'));
            }
            if ($request->query->get('externalId')) {
                $dvd->setExternalId($request->query->get('externalId'));
            }

            $existing = null;
            $titre = trim($dvd->getTitre() ?? '');
            $externalId = $request->query->get('externalId');

            if ($externalId) {
                $existing = $dvdRepo->findOneBy(['externalId' => $externalId]);
            }
            if (!$existing && !empty($titre)) {
                $existing = $dvdRepo->createQueryBuilder('d')
                    ->where('LOWER(d.titre) = LOWER(:titre)')
                    ->setParameter('titre', $titre)
                    ->getQuery()
                    ->getOneOrNullResult();
            }

            if ($existing) {
                $duplicateWarning = [
                    'message' => 'Ce DVD/Blu-ray existe déjà dans la base !',
                    'dvd' => $existing,
                ];
            }

            $prefillImages = $session->get('dvd_prefill_images', []);
        }

        $form = $this->createForm(DvdType::class, $dvd);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $dvdRepo->createQueryBuilder('d')
                ->where('LOWER(d.titre) = LOWER(:titre)')
                ->setParameter('titre', trim($dvd->getTitre()));

            $existing = $qb->getQuery()->getOneOrNullResult();
            if ($existing && $existing->getId() !== $dvd->getId()) {
                $this->addFlash('danger', 'Ce DVD/Blu-ray existe déjà dans la base !');
                return $this->render('dvd/form.html.twig', [
                    'form' => $form->createView(),
                    'dvd' => null,
                    'isEdit' => false,
                    'prefillImages' => $prefillImages,
                    'duplicateWarning' => [
                        'message' => 'Ce DVD/Blu-ray existe déjà !',
                        'dvd' => $existing,
                    ],
                ]);
            }

            // Upload cover
            $coverUpload = $request->files->get('cover_upload');
            if ($coverUpload) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $extension = $coverUpload->guessExtension() ?? 'jpg';
                $filename = 'dvd_' . uniqid() . '.' . $extension;
                try {
                    $coverUpload->move($uploadDir, $filename);
                    $dvd->setCoverUrl('/uploads/dvd/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur upload jaquette: ' . $e->getMessage());
                }
            }

            $this->em->persist($dvd);

            // Ajouter à la collection
            $addToCollection = $request->request->get('add_to_collection', '1');
            if ($addToCollection === '1') {
                $lien = new DvdUserCollection();
                $lien->setUser($this->getUser());
                $lien->setDvd($dvd);

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
                    $userUploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd/user';
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

            $session->remove('dvd_prefill_images');
            $this->em->flush();

            $this->addFlash('success', 'DVD/Blu-ray créé avec succès' . ($addToCollection === '1' ? ' et ajouté à votre collection' : ''));
            return $this->redirectToRoute('dvd_detail', ['id' => $dvd->getId()]);
        }

        return $this->render('dvd/form.html.twig', [
            'form' => $form->createView(),
            'dvd' => $dvd,
            'isEdit' => false,
            'prefillImages' => $prefillImages,
            'duplicateWarning' => $duplicateWarning,
        ]);
    }

    #[Route('/{id}', name: 'dvd_detail', requirements: ['id' => '\d+'])]
    public function detail(int $id, DvdRepository $dvdRepo, DvdUserCollectionRepository $lienRepo): Response
    {
        $dvd = $dvdRepo->find($id);
        if (!$dvd) {
            throw $this->createNotFoundException('DVD/Blu-ray non trouvé');
        }

        $owners = $lienRepo->findByDvd($dvd);
        $userLinks = [];
        if ($this->getUser()) {
            $userLinks = $lienRepo->findBy(['user' => $this->getUser(), 'dvd' => $dvd]);
        }

        return $this->render('dvd/detail.html.twig', [
            'dvd' => $dvd,
            'owners' => $owners,
            'userLinks' => $userLinks,
        ]);
    }

    #[Route('/{id}/modifier', name: 'dvd_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, DvdRepository $dvdRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        $dvd = $dvdRepo->find($id);
        if (!$dvd) {
            throw $this->createNotFoundException('DVD/Blu-ray non trouvé');
        }

        $form = $this->createForm(DvdType::class, $dvd);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverUpload = $request->files->get('cover_upload');
            if ($coverUpload) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $oldCover = $dvd->getCoverUrl();
                if ($oldCover && str_starts_with($oldCover, '/uploads/dvd/')) {
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $oldCover;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $extension = $coverUpload->guessExtension() ?? 'jpg';
                $filename = 'dvd_' . uniqid() . '.' . $extension;
                try {
                    $coverUpload->move($uploadDir, $filename);
                    $dvd->setCoverUrl('/uploads/dvd/' . $filename);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur upload jaquette: ' . $e->getMessage());
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'DVD/Blu-ray modifié avec succès');
            return $this->redirectToRoute('dvd_detail', ['id' => $dvd->getId()]);
        }

        return $this->render('dvd/form.html.twig', [
            'form' => $form->createView(),
            'dvd' => $dvd,
            'isEdit' => true,
            'prefillImages' => [],
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'dvd_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, DvdRepository $dvdRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        $dvd = $dvdRepo->find($id);
        if (!$dvd) {
            throw $this->createNotFoundException('DVD/Blu-ray non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $dvd->getId(), $request->request->get('_token'))) {
            $this->em->remove($dvd);
            $this->em->flush();
            $this->addFlash('success', 'DVD/Blu-ray supprimé');
        }

        return $this->redirectToRoute('dvd_list');
    }

    #[Route('/recherche-api', name: 'dvd_search_api')]
    public function searchApi(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        return $this->render('dvd/search_api.html.twig', [
            'apiConfigured' => $this->dvdApi->isConfigured(),
        ]);
    }

    #[Route('/api/search', name: 'dvd_api_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $query = $request->query->get('q', '');
        $format = $request->query->get('format');

        if (empty($query)) {
            return $this->json(['error' => 'Recherche requise'], 400);
        }

        if (!$this->dvdApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        $results = $this->dvdApi->search($query, $format, 20);

        return $this->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    #[Route('/api/details/{dvdId}', name: 'dvd_api_details', requirements: ['dvdId' => '.+'], methods: ['GET'])]
    public function apiDetails(string $dvdId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        if (!$this->dvdApi->isConfigured()) {
            return $this->json(['error' => 'API non configurée'], 500);
        }

        $details = $this->dvdApi->getDetails($dvdId);

        if (!$details) {
            return $this->json(['error' => 'DVD non trouvé'], 404);
        }

        $session = $request->getSession();
        $images = [];
        if (!empty($details['cover'])) {
            $images[] = ['url' => $details['cover'], 'source' => 'DVDFR'];
        }
        $session->set('dvd_prefill_images', $images);

        return $this->json([
            'success' => true,
            'dvd' => $details,
        ]);
    }

    #[Route('/{id}/ajouter-collection', name: 'dvd_add_owner', requirements: ['id' => '\d+'])]
    public function addOwner(int $id, Request $request, DvdRepository $dvdRepo, DvdUserCollectionRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('dvd');

        $dvd = $dvdRepo->find($id);
        if (!$dvd) {
            throw $this->createNotFoundException('DVD/Blu-ray non trouvé');
        }

        if ($request->isMethod('POST')) {
            $lien = new DvdUserCollection();
            $lien->setUser($this->getUser());
            $lien->setDvd($dvd);

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
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd/user';
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

            $this->addFlash('success', 'DVD/Blu-ray ajouté à votre collection');
            return $this->redirectToRoute('dvd_detail', ['id' => $id]);
        }

        return $this->render('dvd/add_owner.html.twig', [
            'dvd' => $dvd,
        ]);
    }

    #[Route('/{id}/modifier-collection/{lienId}', name: 'dvd_edit_owner', requirements: ['id' => '\d+', 'lienId' => '\d+'])]
    public function editOwner(int $id, int $lienId, Request $request, DvdRepository $dvdRepo, DvdUserCollectionRepository $lienRepo, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($lienId);
        if (!$lien || $lien->getDvd()->getId() !== $id) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        if ($lien->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $dvd = $lien->getDvd();

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
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd/user/' . $lien->getImagePerso();
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dvd/user';
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
            return $this->redirectToRoute('dvd_detail', ['id' => $id]);
        }

        return $this->render('dvd/edit_owner.html.twig', [
            'dvd' => $dvd,
            'lien' => $lien,
        ]);
    }
}
