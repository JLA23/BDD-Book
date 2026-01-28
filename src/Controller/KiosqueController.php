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
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/magazines")
 */
class KiosqueController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="magazines_list")
     */
    public function listMagazines(Request $request, PaginatorInterface $paginator, KioskCollecRepository $repository): Response
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

        return $this->render('magazines/list.html.twig', [
            'pagination' => $pagination,
            'magazines' => $pagination->getItems(),
            'images' => $images,
            'mobile' => $detect->isMobile()
        ]);
    }

    /**
     * @Route("/nouveau", name="magazine_new")
     */
    public function newMagazine(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
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
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageData = file_get_contents($imageFile->getPathname());
                $magazine->setImage($imageData);
            }

            $this->em->persist($magazine);
            $this->em->flush();

            $this->addFlash('success', 'Magazine créé avec succès');
            return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
        }

        return $this->render('magazines/form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $magazine,
            'edit' => false
        ]);
    }

    /**
     * @Route("/{id}", name="magazine_detail", requirements={"id"="\d+"})
     */
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
        $magazineImage = null;
        
        if ($magazine->getImage()) {
            $magazineImage = base64_encode(stream_get_contents($magazine->getImage()));
        }
        
        foreach ($pagination->getItems() as $numero) {
            if ($numero->getCouverture()) {
                $images[$numero->getId()] = base64_encode(stream_get_contents($numero->getCouverture()));
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

    /**
     * @Route("/{id}/modifier", name="magazine_edit", requirements={"id"="\d+"})
     */
    public function editMagazine(int $id, Request $request, KioskCollecRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
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
            if ($imageFile) {
                $imageData = file_get_contents($imageFile->getPathname());
                $magazine->setImage($imageData);
            }

            $this->em->flush();

            $this->addFlash('success', 'Magazine modifié avec succès');
            return $this->redirectToRoute('magazine_detail', ['id' => $magazine->getId()]);
        }

        $currentImage = null;
        if ($magazine->getImage()) {
            $currentImage = base64_encode(stream_get_contents($magazine->getImage()));
        }

        return $this->render('magazines/form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $magazine,
            'currentImage' => $currentImage,
            'edit' => true
        ]);
    }

    /**
     * @Route("/{id}/numero/nouveau", name="numero_new", requirements={"id"="\d+"})
     */
    public function newNumero(int $id, Request $request, KioskCollecRepository $magazineRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
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
            $couvertureFile = $form->get('couvertureFile')->getData();
            if ($couvertureFile) {
                $imageData = file_get_contents($couvertureFile->getPathname());
                $numero->setCouverture($imageData);
            }

            $this->em->persist($numero);
            
            $magazine->setNbnum($magazine->getNbnum() + 1);
            $magazine->setUpdateDate(new \DateTime());
            $magazine->setUpdateUser($user);
            
            $this->em->flush();

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

    /**
     * @Route("/{id}/numeros/nouveau", name="numeros_new_multiple", requirements={"id"="\d+"})
     */
    public function newNumerosMultiple(int $id, Request $request, KioskCollecRepository $magazineRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $magazine = $magazineRepo->find($id);
        if (!$magazine) {
            throw $this->createNotFoundException('Magazine non trouvé');
        }

        $user = $this->getUser();
        
        if ($request->isMethod('POST')) {
            $numerosData = $request->request->all();
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
                    
                    if (!empty($numeroData['dateParution'])) {
                        $numero->setDateParution(new \DateTime($numeroData['dateParution']));
                    }
                    
                    if (!empty($numeroData['EAN'])) {
                        $numero->setEAN($numeroData['EAN']);
                    }
                    
                    if (!empty($numeroData['prix'])) {
                        $numero->setPrix((float) $numeroData['prix']);
                    }
                    
                    if (!empty($numeroData['description'])) {
                        $numero->setDescription($numeroData['description']);
                    }
                    
                    if (!empty($numeroData['commentaire'])) {
                        $numero->setCommentaire($numeroData['commentaire']);
                    }
                    
                    $this->em->persist($numero);
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
            'magazine' => $magazine
        ]);
    }

    /**
     * @Route("/numero/{id}", name="numero_detail", requirements={"id"="\d+"})
     */
    public function detailNumero(int $id, KioskNumRepository $repository, LienKioskNumUserRepository $lienRepo): Response
    {
        $detect = new \Mobile_Detect;
        
        $numero = $repository->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $proprietaires = $lienRepo->findBy(['kioskNum' => $numero]);

        $couvertureImage = null;
        if ($numero->getCouverture()) {
            $couvertureImage = base64_encode(stream_get_contents($numero->getCouverture()));
        }

        return $this->render('magazines/numero_detail.html.twig', [
            'numero' => $numero,
            'magazine' => $numero->getKioskCollec(),
            'couvertureImage' => $couvertureImage,
            'proprietaires' => $proprietaires,
            'mobile' => $detect->isMobile()
        ]);
    }

    /**
     * @Route("/numero/{id}/modifier", name="numero_edit", requirements={"id"="\d+"})
     */
    public function editNumero(int $id, Request $request, KioskNumRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $numero = $repository->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $numero->setUpdateUser($this->getUser());
        $numero->setUpdateDate(new \DateTime());

        $form = $this->createForm(NumeroMagazineType::class, $numero);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $couvertureFile = $form->get('couvertureFile')->getData();
            if ($couvertureFile) {
                $imageData = file_get_contents($couvertureFile->getPathname());
                $numero->setCouverture($imageData);
            }

            $this->em->flush();

            $this->addFlash('success', 'Numéro modifié avec succès');
            return $this->redirectToRoute('numero_detail', ['id' => $numero->getId()]);
        }

        $currentImage = null;
        if ($numero->getCouverture()) {
            $currentImage = base64_encode(stream_get_contents($numero->getCouverture()));
        }

        return $this->render('magazines/numero_form.html.twig', [
            'form' => $form->createView(),
            'magazine' => $numero->getKioskCollec(),
            'numero' => $numero,
            'currentImage' => $currentImage,
            'edit' => true
        ]);
    }

    /**
     * @Route("/numero/{id}/proprietaire/ajouter", name="numero_add_owner", requirements={"id"="\d+"})
     */
    public function addOwner(int $id, Request $request, KioskNumRepository $numeroRepo, UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $numero = $numeroRepo->find($id);
        if (!$numero) {
            throw $this->createNotFoundException('Numéro non trouvé');
        }

        $users = $userRepo->findAll();

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id');
            $commentaire = $request->request->get('commentaire');
            
            $user = $userRepo->find($userId);
            if ($user) {
                $lien = new LienKioskNumUser();
                $lien->setKioskNum($numero);
                $lien->setUser($user);
                $lien->setCommentaire($commentaire);
                
                $this->em->persist($lien);
                $this->em->flush();
                
                $this->addFlash('success', 'Propriétaire ajouté avec succès');
                return $this->redirectToRoute('numero_detail', ['id' => $numero->getId()]);
            }
        }

        return $this->render('magazines/add_owner.html.twig', [
            'numero' => $numero,
            'magazine' => $numero->getKioskCollec(),
            'users' => $users
        ]);
    }

    /**
     * @Route("/recherche", name="magazines_search")
     */
    public function searchMagazines(Request $request, PaginatorInterface $paginator, KioskCollecRepository $magazineRepo, KioskNumRepository $numeroRepo): Response
    {
        $detect = new \Mobile_Detect;
        $search = $request->query->get('q', '');
        $type = $request->query->get('type', 'magazine');
        
        $results = [];
        $images = [];
        
        if (!empty($search)) {
            if ($type === 'magazine') {
                $results = $magazineRepo->searchByName($search);
                foreach ($results as $magazine) {
                    if ($magazine->getImage()) {
                        $images[$magazine->getId()] = base64_encode(stream_get_contents($magazine->getImage()));
                    }
                }
            } else {
                $results = $numeroRepo->searchByMagazineOrNum($search);
                foreach ($results as $numero) {
                    if ($numero->getCouverture()) {
                        $images[$numero->getId()] = base64_encode(stream_get_contents($numero->getCouverture()));
                    }
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
            'search' => $search,
            'type' => $type,
            'mobile' => $detect->isMobile()
        ]);
    }
}