<?php

namespace App\Controller;

use App\Entity\KioskCollec;
use App\Entity\KioskNum;
use App\Entity\LienKioskNumUser;
use App\Entity\User;
use App\Form\MagazineType;
use App\Form\NumeroMagazineType;
use App\Form\NumerosMultiplesType;
use App\Repository\KioskCollecRepository;
use App\Repository\KioskNumRepository;
use App\Repository\LienKioskNumUserRepository;
use App\Repository\UserRepository;
use App\Service\Media\MediaImageSyncService;
use App\Service\SectionPermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/magazines')]
class KiosqueController extends AbstractController
{
    private EntityManagerInterface $em;
    private SectionPermissionService $permissionService;
    private MediaImageSyncService $mediaImageSync;

    public function __construct(
        EntityManagerInterface $em,
        SectionPermissionService $permissionService,
        MediaImageSyncService $mediaImageSync,
    ) {
        $this->em = $em;
        $this->permissionService = $permissionService;
        $this->mediaImageSync = $mediaImageSync;
    }

    #[Route('/', name: 'magazines_list')]
    public function listMagazines(Request $request, PaginatorInterface $paginator, KioskCollecRepository $repository, UserRepository $userRepo): Response
    {
        $detect = new \Mobile_Detect;
        $users = $userRepo->findAll();

        return $this->render('magazines/list.html.twig', [
            'users' => $users,
            'mobile' => $detect->isMobile()
        ]);
    }

    #[Route('/tous', name: 'magazines_all')]
    public function allMagazines(Request $request, PaginatorInterface $paginator, KioskCollecRepository $repository): Response
    {
        $detect = new \Mobile_Detect;
        
        $query = $repository->findBy([], ['nom' => 'ASC']);
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );
        
        $images = [];
        foreach ($pagination->getItems() as $magazine) {
            if ($magazine->getImage()) {
                $images[$magazine->getId()] = base64_encode(stream_get_contents($magazine->getImage()));
            }
        }

        return $this->render('magazines/all.html.twig', [
            'pagination' => $pagination,
            'magazines' => $pagination->getItems(),
            'images' => $images,
            'mobile' => $detect->isMobile()
        ]);
    }

    #[Route('/nouveau', name: 'magazine_new')]
    public function newMagazine(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $user = $this->getUser();
        $magazine = new KioskCollec();
        $magazine->setCreateUser($user);
        $magazine->setUpdateUser($user);
        $magazine->setCreateDate(new \DateTime());
        $magazine->setUpdateDate(new \DateTime());
        $magazine->setNbnum(0);
        $magazine->setStatut(true);

        $form = $this->createForm(MagazineType::class, $magazine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageData = null;
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageData = file_get_contents($imageFile->getPathname());
                $magazine->setImage($imageData);
            }

            $this->em->persist($magazine);
            $this->em->flush();
            if ($imageData !== null) {
                $this->mediaImageSync->syncKioskCollecImage($magazine, $imageData);
                $this->em->flush();
            }

            $this->addFlash('success', 'Magazine créé avec succès');
            return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
        }

        return $this->render('magazines/form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $magazine,
            'edit' => false
        ]);
    }

    #[Route('/{id}', name: 'magazine_detail', requirements: ['id' => '\d+'])]
    public function detailMagazine(int $id, Request $request, PaginatorInterface $paginator, KioskCollecRepository $magazineRepo, KioskNumRepository $numeroRepo): Response
    {
        $detect = new \Mobile_Detect;
        
        $magazine = $magazineRepo->find($id);
        if (!$magazine) {
            throw $this->createNotFoundException('Magazine non trouvé');
        }

        $numeros = $numeroRepo->findBy(['kioskCollec' => $magazine], ['dateParution' => 'DESC', 'num' => 'DESC']);
        
        $pagination = $paginator->paginate(
            $numeros,
            $request->query->getInt('page', 1),
            20
        );

        $images = [];
        $magazineImage = $this->resolveKioskImageSrc($magazine->getStoredPath(), $magazine->getImage());

        foreach ($pagination->getItems() as $numero) {
            $src = $this->resolveKioskImageSrc($numero->getStoredPath(), $numero->getCouverture());
            if ($src !== null) {
                $images[$numero->getId()] = $src;
            }
        }

        return $this->render('magazines/detail.html.twig', [
            'magazine' => $magazine,
            'magazineImage' => $magazineImage,
            'pagination' => $pagination,
            'numeros' => $pagination->getItems(),
            'images' => $images,
            'mobile' => $detect->isMobile()
        ]);
    }

    #[Route('/{id}/modifier', name: 'magazine_edit', requirements: ['id' => '\d+'])]
    public function editMagazine(int $id, Request $request, KioskCollecRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $magazine = $repository->find($id);
        if (!$magazine) {
            throw $this->createNotFoundException('Magazine non trouvé');
        }

        $magazine->setUpdateUser($this->getUser());
        $magazine->setUpdateDate(new \DateTime());

        $form = $this->createForm(MagazineType::class, $magazine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            $imageData = null;
            if ($imageFile) {
                $imageData = file_get_contents($imageFile->getPathname());
                $magazine->setImage($imageData);
            }

            $this->em->flush();
            if ($imageData !== null) {
                $this->mediaImageSync->syncKioskCollecImage($magazine, $imageData);
                $this->em->flush();
            }

            $this->addFlash('success', 'Magazine modifié avec succès');
            return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
        }

        $currentImage = $this->resolveKioskImageSrc($magazine->getStoredPath(), $magazine->getImage());

        return $this->render('magazines/form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $magazine,
            'currentImage' => $currentImage,
            'edit' => true
        ]);
    }

    #[Route('/{id}/numero/nouveau', name: 'numero_new', requirements: ['id' => '\d+'])]
    public function newNumero(int $id, Request $request, KioskCollecRepository $magazineRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $magazine = $magazineRepo->find($id);
        if (!$magazine) {
            throw $this->createNotFoundException('Magazine non trouvé');
        }

        $user = $this->getUser();
        $numero = new KioskNum();
        $numero->setKioskCollec($magazine);
        $numero->setCreateUser($user);
        $numero->setUpdateUser($user);
        $numero->setCreateDate(new \DateTime());
        $numero->setUpdateDate(new \DateTime());

        $form = $this->createForm(NumeroMagazineType::class, $numero);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $couvertureBinary = null;
            $couvertureFile = $form->get('couvertureFile')->getData();
            if ($couvertureFile) {
                $couvertureBinary = file_get_contents($couvertureFile->getPathname());
                $numero->setCouverture($couvertureBinary);
            }

            $this->em->persist($numero);

            // Le créateur devient automatiquement propriétaire du numéro
            $lien = new LienKioskNumUser();
            $lien->setKioskNum($numero);
            $lien->setUser($user);
            $this->em->persist($lien);
            
            $magazine->setNbnum($magazine->getNbnum() + 1);
            $magazine->setUpdateDate(new \DateTime());
            $magazine->setUpdateUser($user);
            
            $this->em->flush();
            if ($couvertureBinary !== null) {
                $this->mediaImageSync->syncKioskNumCover($numero, $couvertureBinary);
                $this->em->flush();
            }

            $this->addFlash('success', 'Numéro ajouté avec succès');
            return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
        }

        return $this->render('magazines/numero_form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $magazine,
            'numero' => $numero,
            'edit' => false
        ]);
    }

    #[Route('/{id}/numeros/nouveau', name: 'numeros_new_multiple', requirements: ['id' => '\d+'])]
    public function newNumerosMultiple(int $id, Request $request, KioskCollecRepository $magazineRepo, UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $magazine = $magazineRepo->find($id);
        if (!$magazine) {
            throw $this->createNotFoundException('Magazine non trouvé');
        }

        $user = $this->getUser();
        $users = $userRepo->findAll();
        
        if ($request->isMethod('POST')) {
            $numerosData = $request->request->all();
            $files = $request->files->all();
            $count = 0;
            
            if (isset($numerosData['numeros']) && is_array($numerosData['numeros'])) {
                foreach ($numerosData['numeros'] as $index => $numeroData) {
                    if (empty($numeroData['num'])) {
                        continue;
                    }
                    
                    $numero = new KioskNum();
                    $numero->setKioskCollec($magazine);
                    $numero->setCreateUser($user);
                    $numero->setUpdateUser($user);
                    $numero->setCreateDate(new \DateTime());
                    $numero->setUpdateDate(new \DateTime());
                    $numero->setNum((int) $numeroData['num']);
                    
                    $dateParution = $this->parseMonthYear($numeroData['dateParution'] ?? null);
                    if ($dateParution !== null) {
                        $numero->setDateParution($dateParution);
                    }
                    
                    if (!empty($numeroData['EAN'])) {
                        $numero->setEAN($numeroData['EAN']);
                    }
                    
                    if (!empty($numeroData['prix'])) {
                        $numero->setPrix((float) $numeroData['prix']);
                    }
                    
                    if (!empty($numeroData['monnaie'])) {
                        $monnaie = $this->em->getRepository(\App\Entity\Monnaie::class)->find($numeroData['monnaie']);
                        if ($monnaie) {
                            $numero->setMonnaie($monnaie);
                        }
                    }
                    
                    if (!empty($numeroData['description'])) {
                        $numero->setDescription($numeroData['description']);
                    }
                    
                    if (!empty($numeroData['commentaire'])) {
                        $numero->setCommentaire($numeroData['commentaire']);
                    }
                    
                    $couvertureBinary = null;
                    if (isset($files['numeros'][$index]['image']) && $files['numeros'][$index]['image']) {
                        $couvertureBinary = file_get_contents($files['numeros'][$index]['image']->getPathname());
                        $numero->setCouverture($couvertureBinary);
                    }

                    $this->em->persist($numero);
                    $this->em->flush(); // Flush pour obtenir l'ID du numéro

                    if ($couvertureBinary !== null) {
                        $this->mediaImageSync->syncKioskNumCover($numero, $couvertureBinary);
                    }
                    
                    // Gérer les propriétaires
                    if (!empty($numeroData['users']) && is_array($numeroData['users'])) {
                        $commentaires = $numeroData['commentaires'] ?? [];
                        $prixAchats = $numeroData['prix_achat'] ?? [];
                        $datesAchat = $numeroData['dates_achat'] ?? [];

                        foreach ($numeroData['users'] as $userId) {
                            $proprietaire = $userRepo->find($userId);
                            if ($proprietaire) {
                                $lien = new LienKioskNumUser();
                                $lien->setKioskNum($numero);
                                $lien->setUser($proprietaire);
                                $this->applyOwnerFields(
                                    $lien,
                                    $commentaires[$userId] ?? null,
                                    $prixAchats[$userId] ?? null,
                                    $datesAchat[$userId] ?? null
                                );
                                $this->em->persist($lien);
                            }
                        }
                    }
                    
                    $count++;
                }
                
                if ($count > 0) {
                    $magazine->setNbnum($magazine->getNbnum() + $count);
                    $magazine->setUpdateDate(new \DateTime());
                    $magazine->setUpdateUser($user);
                    
                    $this->em->flush();
                    
                    $this->addFlash('success', $count . ' numéro(s) ajouté(s) avec succès');
                    return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
                }
            }
            
            $this->addFlash('warning', 'Aucun numéro valide à ajouter');
        }

        return $this->render('magazines/numeros_multiple_form.html.twig', [
            'magazine' => $magazine,
            'users' => $users
        ]);
    }

    #[Route('/numero/{id}', name: 'numero_detail', requirements: ['id' => '\d+'])]
    public function detailNumero(int $id, KioskNumRepository $repository, LienKioskNumUserRepository $lienRepo): Response
    {
        $detect = new \Mobile_Detect;
        
        $numero = $repository->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $proprietaires = $lienRepo->findByNumero($numero);

        $couvertureImage = $this->resolveKioskImageSrc($numero->getStoredPath(), $numero->getCouverture());

        return $this->render('magazines/numero_detail.html.twig', [
            'numero' => $numero,
            'magazine' => $numero->getKioskCollec(),
            'couvertureImage' => $couvertureImage,
            'proprietaires' => $proprietaires,
            'mobile' => $detect->isMobile()
        ]);
    }

    #[Route('/numero/{id}/modifier', name: 'numero_edit', requirements: ['id' => '\d+'])]
    public function editNumero(int $id, Request $request, KioskNumRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $numero = $repository->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $numero->setUpdateUser($this->getUser());
        $numero->setUpdateDate(new \DateTime());

        $form = $this->createForm(NumeroMagazineType::class, $numero);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $couvertureBinary = null;
            $couvertureFile = $form->get('couvertureFile')->getData();
            if ($couvertureFile) {
                $couvertureBinary = file_get_contents($couvertureFile->getPathname());
                $numero->setCouverture($couvertureBinary);
            }

            $this->em->flush();
            if ($couvertureBinary !== null) {
                $this->mediaImageSync->syncKioskNumCover($numero, $couvertureBinary);
                $this->em->flush();
            }

            $this->addFlash('success', 'Numéro modifié avec succès');
            return $this->redirectToRoute('numero_detail', ['id' => $numero->getId()]);
        }

        $currentImage = $this->resolveKioskImageSrc($numero->getStoredPath(), $numero->getCouverture());

        return $this->render('magazines/numero_form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $numero->getKioskCollec(),
            'numero' => $numero,
            'currentImage' => $currentImage,
            'edit' => true
        ]);
    }

    #[Route('/numero/{id}/proprietaire/ajouter', name: 'numero_add_owner', requirements: ['id' => '\d+'])]
    public function addOwner(int $id, Request $request, KioskNumRepository $numeroRepo, UserRepository $userRepo, LienKioskNumUserRepository $lienRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('magazines');
        
        $numero = $numeroRepo->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $users = $userRepo->findAll();
        
        // Récupérer les propriétaires existants avec leurs commentaires
        $existingOwners = $lienRepo->findBy(['kioskNum' => $numero]);
        $ownersData = [];
        foreach ($existingOwners as $lien) {
            $ownersData[$lien->getUser()->getId()] = [
                'lienId' => $lien->getId(),
                'commentaire' => $lien->getCommentaire(),
                'prixAchat' => $lien->getPrixAchat(),
                'dateAchat' => $lien->getDateAchat(),
            ];
        }

        if ($request->isMethod('POST')) {
            $userIds = $request->request->all('user_ids') ?: [];
            $commentaires = $request->request->all('commentaires') ?: [];
            $prixAchats = $request->request->all('prix_achat') ?: [];
            $datesAchat = $request->request->all('dates_achat') ?: [];
            
            if (empty($userIds)) {
                $this->addFlash('warning', 'Veuillez sélectionner au moins un utilisateur');
                return $this->render('magazines/add_owner.html.twig', [
                    'numero' => $numero,
                    'magazine' => $numero->getKioskCollec(),
                    'users' => $users,
                    'ownersData' => $ownersData
                ]);
            }
            
            $countAdded = 0;
            $countUpdated = 0;
            
            // Traiter les utilisateurs sélectionnés
            foreach ($userIds as $userId) {
                $user = $userRepo->find($userId);
                if ($user) {
                    $commentaire = $commentaires[$userId] ?? '';
                    
                    // Vérifier si le lien existe déjà
                    $existingLien = $lienRepo->findOneBy(['kioskNum' => $numero, 'user' => $user]);
                    
                    if ($existingLien) {
                        $this->applyOwnerFields($existingLien, $commentaire, $prixAchats[$userId] ?? null, $datesAchat[$userId] ?? null);
                        $countUpdated++;
                    } else {
                        $lien = new LienKioskNumUser();
                        $lien->setKioskNum($numero);
                        $lien->setUser($user);
                        $this->applyOwnerFields($lien, $commentaire, $prixAchats[$userId] ?? null, $datesAchat[$userId] ?? null);
                        $this->em->persist($lien);
                        $countAdded++;
                    }
                }
            }
            
            // Supprimer les propriétaires décochés
            foreach ($existingOwners as $lien) {
                if (!in_array($lien->getUser()->getId(), $userIds)) {
                    $this->em->remove($lien);
                }
            }
            
            $this->em->flush();
            
            if ($countAdded > 0 || $countUpdated > 0) {
                $message = '';
                if ($countAdded > 0) $message .= $countAdded . ' propriétaire(s) ajouté(s). ';
                if ($countUpdated > 0) $message .= $countUpdated . ' commentaire(s) mis à jour.';
                $this->addFlash('success', $message);
            } else {
                $this->addFlash('info', 'Aucune modification effectuée');
            }
            
            return $this->redirectToRoute('numero_detail', ['id' => $numero->getId()]);
        }

        return $this->render('magazines/add_owner.html.twig', [
            'numero' => $numero,
            'magazine' => $numero->getKioskCollec(),
            'users' => $users,
            'ownersData' => $ownersData
        ]);
    }

    #[Route('/proprietaire/{id}/modifier', name: 'numero_edit_owner', requirements: ['id' => '\d+'])]
    public function editOwner(int $id, Request $request, LienKioskNumUserRepository $lienRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($id);
        if (!$lien) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        if ($lien->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $numero = $lien->getKioskNum();
        $magazine = $numero->getKioskCollec();

        if ($request->isMethod('POST')) {
            $this->applyOwnerFields(
                $lien,
                $request->request->get('commentaire'),
                $request->request->get('prix_achat'),
                $request->request->get('date_achat')
            );
            $this->em->flush();

            $this->addFlash('success', 'Propriété mise à jour');
            return $this->redirectToRoute('numero_detail', ['id' => $numero->getId()]);
        }

        return $this->render('magazines/edit_owner.html.twig', [
            'lien' => $lien,
            'numero' => $numero,
            'magazine' => $magazine,
        ]);
    }

    #[Route('/proprietaire/{id}/commentaire', name: 'numero_edit_owner_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editOwnerComment(int $id, Request $request, LienKioskNumUserRepository $lienRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $lien = $lienRepo->find($id);
        if (!$lien) {
            throw $this->createNotFoundException('Lien non trouvé');
        }

        if ($lien->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $lien->setCommentaire($request->request->get('commentaire'));
            $this->em->flush();
            $this->addFlash('success', 'Commentaire mis à jour');
        }

        return $this->redirectToRoute('numero_detail', ['id' => $lien->getKioskNum()->getId()]);
    }

    #[Route('/utilisateur/{id}', name: 'magazines_user', requirements: ['id' => '\d+'])]
    public function userCollection(int $id, UserRepository $userRepo, LienKioskNumUserRepository $lienRepo): Response
    {
        $user = $userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $liens = $lienRepo->findByUserWithNumero($user);

        return $this->render('magazines/user_collection.html.twig', [
            'user' => $user,
            'liens' => $liens,
        ]);
    }

    #[Route('/recherche', name: 'magazines_search')]
    public function searchMagazines(Request $request, PaginatorInterface $paginator, KioskCollecRepository $magazineRepo, UserRepository $userRepo): Response
    {
        $detect = new \Mobile_Detect;
        $users = $userRepo->findAll();
        
        $searchQuery = $request->query->get('q', '');
        $userId = $request->query->get('user', '0');
        $userName = null;
        $userIdInt = null;
        
        if ($userId != '0' && !empty($userId)) {
            $userIdInt = (int) $userId;
            $userEntity = $userRepo->find($userId);
            if ($userEntity) {
                $userName = $userEntity->getName() . ' ' . $userEntity->getLastName();
            }
        }
        
        $results = [];
        $images = [];
        $totalResults = 0;
        
        if (!empty($searchQuery)) {
            $results = $magazineRepo->searchByNameAndUser($searchQuery, $userIdInt);
            $totalResults = count($results);
            
            foreach ($results as $magazine) {
                if ($magazine->getImage()) {
                    $images[$magazine->getId()] = base64_encode(stream_get_contents($magazine->getImage()));
                }
            }
        }

        $pagination = $paginator->paginate(
            $results,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('magazines/search.html.twig', [
            'pagination' => $pagination,
            'results' => $pagination->getItems(),
            'images' => $images,
            'searchQuery' => $searchQuery,
            'searchUser' => $userName,
            'searchUserId' => $userIdInt,
            'users' => $users,
            'totalResults' => $totalResults,
            'mobile' => $detect->isMobile()
        ]);
    }

    private function parseMonthYear(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $date = \DateTime::createFromFormat('Y-m-d', $value . '-01');

            return $date ?: null;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveKioskImageSrc(?string $storedPath, mixed $blob): ?string
    {
        if ($storedPath !== null && $storedPath !== '') {
            return $storedPath;
        }

        if ($blob === null) {
            return null;
        }

        if (is_resource($blob)) {
            rewind($blob);
            $content = stream_get_contents($blob);
        } elseif (is_string($blob)) {
            $content = $blob;
        } else {
            $content = false;
        }

        if ($content === false || $content === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($content);
    }

    private function applyOwnerFields(
        LienKioskNumUser $lien,
        ?string $commentaire,
        mixed $prixAchat,
        mixed $dateAchat
    ): void {
        $lien->setCommentaire($commentaire !== '' ? $commentaire : null);

        if ($prixAchat !== null && $prixAchat !== '') {
            $lien->setPrixAchat((float) $prixAchat);
        } else {
            $lien->setPrixAchat(null);
        }

        if ($dateAchat !== null && $dateAchat !== '') {
            $lien->setDateAchat(new \DateTime((string) $dateAchat));
        } else {
            $lien->setDateAchat(null);
        }
    }
}