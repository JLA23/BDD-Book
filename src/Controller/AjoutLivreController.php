<?php

namespace App\Controller;

use App\Entity\Auteur;
use App\Entity\Category;
use App\Entity\Collection;
use App\Entity\Edition;
use App\Entity\LienAuteurLivre;
use App\Entity\LienUserLivre;
use App\Entity\Livre;
use App\Form\LivreType;
use App\Service\BookCoverService;
use App\Service\BookInfoScraperService;
use App\Service\SectionPermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/livre/ajouter')]
class AjoutLivreController extends AbstractController
{
    private EntityManagerInterface $em;
    private BookInfoScraperService $scraperService;
    private BookCoverService $coverService;
    private SectionPermissionService $permissionService;

    public function __construct(
        EntityManagerInterface $em,
        BookInfoScraperService $scraperService,
        BookCoverService $coverService,
        SectionPermissionService $permissionService
    ) {
        $this->em = $em;
        $this->scraperService = $scraperService;
        $this->coverService = $coverService;
        $this->permissionService = $permissionService;
    }

    /**
     * Page d'accueil : choix du mode d'ajout
     */
    #[Route('', name: 'livre_ajouter')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        return $this->render('livres/ajouter_choix.html.twig', [
            'supportedSites' => $this->scraperService->getSupportedSites(),
        ]);
    }

    /**
     * Recherche par ISBN ou titre (scraping automatique)
     */
    #[Route('/recherche', name: 'livre_ajouter_recherche', methods: ['GET', 'POST'])]
    public function recherche(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $query = $request->get('query', '');
        $noResults = false;
        $existingBooks = [];

        if ($request->isMethod('POST') && !empty($query)) {
            $isbn = preg_replace('/[^0-9X]/i', '', $query);

            // 1. D'abord chercher en base de données
            $conn = $this->em->getConnection();
            $sql = "SELECT id FROM livre WHERE REPLACE(isbn, '-', '') = :isbn";
            $ids = $conn->executeQuery($sql, ['isbn' => $isbn])->fetchFirstColumn();

            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $livre = $this->em->getRepository(Livre::class)->find($id);
                    if ($livre) {
                        $existingBooks[] = $livre;
                    }
                }
            }

            // Si des livres existent déjà en base, les proposer à l'utilisateur
            if (!empty($existingBooks)) {
                return $this->render('livres/ajouter_recherche.html.twig', [
                    'query' => $query,
                    'noResults' => false,
                    'existingBooks' => $existingBooks,
                ]);
            }

            // 2. Sinon, scraper les sources externes
            $merged = $this->scraperService->searchByIsbn($query);
            if ($merged) {
                return $this->redirectToFormWithData($merged, $request);
            }
            $noResults = true;
        }

        return $this->render('livres/ajouter_recherche.html.twig', [
            'query' => $query,
            'noResults' => $noResults,
            'existingBooks' => $existingBooks,
        ]);
    }

    /**
     * Scraping depuis une URL donnée
     */
    #[Route('/url', name: 'livre_ajouter_url', methods: ['GET', 'POST'])]
    public function fromUrl(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $data = null;
        $error = null;
        $url = $request->get('url', '');

        if ($request->isMethod('POST') && !empty($url)) {
            if (!$this->scraperService->isUrlSupported($url)) {
                $error = 'Ce site n\'est pas supporté. Sites acceptés : Amazon, Fnac, Babelio, Bedetheque.';
            } else {
                $data = $this->scraperService->scrapeFromUrl($url);
                if (!$data) {
                    $error = 'Impossible de récupérer les informations depuis cette URL. Vérifiez le lien.';
                }
            }
        }

        if ($data) {
            return $this->redirectToFormWithData($data, $request);
        }

        return $this->render('livres/ajouter_url.html.twig', [
            'url' => $url,
            'error' => $error,
            'supportedSites' => $this->scraperService->getSupportedSites(),
        ]);
    }

    /**
     * Formulaire d'ajout (manuel ou pré-rempli)
     */
    #[Route('/formulaire', name: 'livre_ajouter_formulaire', methods: ['GET', 'POST'])]
    public function formulaire(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $livre = new Livre();
        $forceNew = $request->get('force_new', false);

        // Définir la monnaie par défaut (Euros)
        $monnaieEuros = $this->em->getRepository(\App\Entity\Monnaie::class)->findOneBy(['libelle' => 'Euros']);
        if (!$monnaieEuros) {
            $monnaieEuros = $this->em->getRepository(\App\Entity\Monnaie::class)->findOneBy(['libelle' => 'Euro']);
        }
        if ($monnaieEuros) {
            $livre->setMonnaie($monnaieEuros);
        }

        // Pré-remplir depuis les paramètres GET (données scrapées)
        $prefill = $request->query->all();
        
        // Récupérer le résumé complet depuis la session (stocké car trop long pour l'URL)
        $sessionResume = $request->getSession()->get('livre_prefill_resume');
        if ($sessionResume) {
            $prefill['resume'] = $sessionResume;
            $request->getSession()->remove('livre_prefill_resume');
        }
        
        if (!empty($prefill)) {
            $this->prefillLivre($livre, $prefill);
        }

        $form = $this->createForm(LivreType::class, $livre);

        // Pré-remplir les champs non-mappés
        if (!empty($prefill['auteurs'])) {
            $form->get('auteurs')->setData($prefill['auteurs']);
        }
        if (!empty($prefill['imageUrl'])) {
            $form->get('imageUrl')->setData($prefill['imageUrl']);
        }
        
        // Pré-remplir le prix d'achat et la monnaie avec les valeurs du livre
        if ($livre->getPrixBase()) {
            $form->get('prixAchat')->setData($livre->getPrixBase());
        }
        if ($livre->getMonnaie()) {
            $form->get('monnaieAchat')->setData($livre->getMonnaie());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier les doublons sauf si force_new
            if (!$forceNew) {
                $doublons = $this->findDoublons($livre);
                if (!empty($doublons)) {
                    // Stocker les données du formulaire en session pour les retrouver
                    $formData = $this->extractFormData($form, $livre);
                    $request->getSession()->set('livre_ajout_data', $formData);

                    return $this->render('livres/ajouter_doublon.html.twig', [
                        'doublons' => $doublons,
                        'nouveauLivre' => $livre,
                        'formData' => $formData,
                    ]);
                }
            }

            return $this->saveLivre($form, $livre);
        }

        return $this->render('livres/ajouter_formulaire.html.twig', [
            'form' => $form->createView(),
            'imageUrl' => $prefill['imageUrl'] ?? $form->get('imageUrl')->getData(),
            'source' => $prefill['source'] ?? null,
        ]);
    }

    /**
     * Confirmer la création d'une nouvelle fiche malgré les doublons
     */
    #[Route('/confirmer-nouveau', name: 'livre_ajouter_confirmer_nouveau', methods: ['POST'])]
    public function confirmerNouveau(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $formData = $request->getSession()->get('livre_ajout_data');
        if (!$formData) {
            $this->addFlash('warning', 'Données expirées. Veuillez recommencer.');
            return $this->redirectToRoute('livre_ajouter');
        }
        $request->getSession()->remove('livre_ajout_data');

        $livre = new Livre();
        $this->prefillLivre($livre, $formData);
        $this->handleImage($livre, $formData);
        $this->em->persist($livre);
        $this->handleAuteurs($livre, $formData['auteurs'] ?? '');
        $this->createLienUser($livre, $formData);
        $this->em->flush();

        $this->addFlash('warning', 'Le livre "' . $livre->getTitre() . '" a été créé et ajouté à votre bibliothèque !');
        return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
    }

    /**
     * Associer un livre existant à l'utilisateur courant
     */
    #[Route('/associer/{id}', name: 'livre_ajouter_associer', methods: ['GET', 'POST'])]
    public function associer(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $livre = $this->em->getRepository(Livre::class)->find($id);
        if (!$livre) {
            $this->addFlash('warning', 'Livre introuvable.');
            return $this->redirectToRoute('livre_ajouter');
        }

        // Vérifier que l'utilisateur ne possède pas déjà ce livre
        $existingLien = $this->em->getRepository(LienUserLivre::class)->findOneBy([
            'user' => $this->getUser(),
            'livre' => $livre,
        ]);
        if ($existingLien) {
            $this->addFlash('warning', 'Vous possédez déjà ce livre dans votre bibliothèque.');
            return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
        }

        // Récupérer les données en session (date, commentaire, prix)
        $formData = $request->getSession()->get('livre_ajout_data', []);
        $request->getSession()->remove('livre_ajout_data');

        $this->createLienUser($livre, $formData);
        $this->em->flush();

        $this->addFlash('warning', 'Le livre "' . $livre->getTitre() . '" a été ajouté à votre bibliothèque !');
        return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
    }

    /**
     * Pré-remplissage depuis un résultat de recherche (via POST JSON)
     */
    #[Route('/prefill', name: 'livre_ajouter_prefill', methods: ['POST'])]
    public function prefill(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        $this->permissionService->denyAccessUnlessCanRegister('books');

        $data = $request->request->all();

        return $this->redirectToFormWithData($data, $request);
    }

    // ===================== PRIVATE METHODS =====================

    private function redirectToFormWithData(array $data, Request $request = null): Response
    {
        $params = [];

        if (!empty($data['titre'])) $params['titre'] = $data['titre'];
        if (!empty($data['tome'])) $params['tome'] = $data['tome'];
        if (!empty($data['isbn'])) $params['isbn'] = $data['isbn'];
        if (!empty($data['annee'])) $params['annee'] = $data['annee'];
        if (!empty($data['pages'])) $params['pages'] = $data['pages'];
        if (!empty($data['prix'])) $params['prix'] = $data['prix'];
        if (!empty($data['image'])) $params['imageUrl'] = $data['image'];
        if (!empty($data['imageUrl'])) $params['imageUrl'] = $data['imageUrl'];
        if (!empty($data['editeur'])) $params['editeur'] = $data['editeur'];
        if (!empty($data['source'])) $params['source'] = $data['source'];
        if (!empty($data['sourceUrl'])) $params['sourceUrl'] = $data['sourceUrl'];

        // Auteurs : tableau -> chaîne séparée par virgules
        if (!empty($data['auteurs'])) {
            if (is_array($data['auteurs'])) {
                $params['auteurs'] = implode(', ', $data['auteurs']);
            } else {
                $params['auteurs'] = $data['auteurs'];
            }
        }

        // Stocker le résumé complet en session (trop long pour l'URL)
        if (!empty($data['resume']) && $request) {
            $request->getSession()->set('livre_prefill_resume', $data['resume']);
        }

        return $this->redirectToRoute('livre_ajouter_formulaire', $params);
    }

    private function prefillLivre(Livre $livre, array $data): void
    {
        if (!empty($data['titre'])) $livre->setTitre($data['titre']);
        if (!empty($data['tome'])) $livre->setTome((int) $data['tome']);
        if (!empty($data['isbn'])) $livre->setIsbn($data['isbn']);
        if (!empty($data['annee'])) $livre->setAnnee((int) $data['annee']);
        if (!empty($data['pages'])) $livre->setPages((int) $data['pages']);
        if (!empty($data['prix'])) $livre->setPrixBase((float) $data['prix']);
        if (!empty($data['resume'])) $livre->setResume($data['resume']);
        if (!empty($data['sourceUrl'])) $livre->setAmazon($data['sourceUrl']);

        // Éditeur : chercher ou créer
        if (!empty($data['editeur'])) {
            $edition = $this->em->getRepository(Edition::class)->findOneBy(['nom' => $data['editeur']]);
            if ($edition) {
                $livre->setEdition($edition);
            }
        }
    }

    /**
     * Cherche des livres existants qui correspondent (par ISBN ou titre similaire)
     */
    private function findDoublons(Livre $livre): array
    {
        $doublons = [];
        $conn = $this->em->getConnection();

        // Recherche par ISBN exact (requête SQL native pour éviter le bug REPLACE en DQL)
        if (!empty($livre->getIsbn())) {
            $isbn = str_replace('-', '', trim($livre->getIsbn()));
            $sql = "SELECT id FROM livre WHERE REPLACE(isbn, '-', '') = :isbn";
            $ids = $conn->executeQuery($sql, ['isbn' => $isbn])->fetchFirstColumn();
            foreach ($ids as $id) {
                $l = $this->em->getRepository(Livre::class)->find($id);
                if ($l) $doublons[$l->getId()] = $l;
            }
        }

        // Recherche par titre exact (insensible à la casse)
        if (!empty($livre->getTitre())) {
            $byTitre = $this->em->getRepository(Livre::class)->createQueryBuilder('l')
                ->where('UPPER(l.titre) = UPPER(:titre)')
                ->setParameter('titre', trim($livre->getTitre()))
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
            foreach ($byTitre as $l) {
                $doublons[$l->getId()] = $l;
            }
        }

        return array_values($doublons);
    }

    /**
     * Extrait toutes les données du formulaire pour stockage en session
     */
    private function extractFormData($form, Livre $livre): array
    {
        return [
            'titre' => $livre->getTitre(),
            'isbn' => $livre->getIsbn(),
            'annee' => $livre->getAnnee(),
            'pages' => $livre->getPages(),
            'resume' => $livre->getResume(),
            'amazon' => $livre->getAmazon(),
            'cycle' => $livre->getCycle(),
            'tome' => $livre->getTome(),
            'prixBase' => $livre->getPrixBase(),
            'category_id' => $livre->getCategory() ? $livre->getCategory()->getId() : null,
            'collection_id' => $livre->getCollection() ? $livre->getCollection()->getId() : null,
            'edition_id' => $livre->getEdition() ? $livre->getEdition()->getId() : null,
            'monnaie_id' => $livre->getMonnaie() ? $livre->getMonnaie()->getId() : null,
            'auteurs' => $form->get('auteurs')->getData(),
            'imageUrl' => $form->get('imageUrl')->getData(),
            'dateAchat' => $form->get('dateAchat')->getData() ? $form->get('dateAchat')->getData()->format('Y-m-d') : null,
            'prixAchat' => $form->get('prixAchat')->getData(),
            'monnaieAchat' => $form->get('monnaieAchat')->getData() ? $form->get('monnaieAchat')->getData()->getId() : null,
            'commentaire' => $form->get('commentaire')->getData(),
        ];
    }

    private function handleImage(Livre $livre, array $data): void
    {
        if (!empty($data['imageUrl'])) {
            $imageContent = $this->coverService->downloadImage($data['imageUrl']);
            if ($imageContent) {
                $extension = 'jpg';
                if (preg_match('/\.(png|gif|webp|jpeg)/i', $data['imageUrl'], $m)) {
                    $extension = strtolower($m[1]);
                }
                $filename = uniqid() . '.' . $extension;
                $path = $this->getParameter('kernel.project_dir') . '/public/uploads/covers/' . $filename;
                file_put_contents($path, $imageContent);
                $livre->setImage2($filename);
            }
        }
    }

    private function handleAuteurs(Livre $livre, ?string $auteursStr): void
    {
        if (empty($auteursStr)) return;

        $auteurNoms = array_map('trim', explode(',', $auteursStr));
        foreach ($auteurNoms as $nom) {
            if (empty($nom)) continue;

            $auteur = $this->em->getRepository(Auteur::class)->findOneBy(['nom' => $nom]);
            if (!$auteur) {
                $auteur = new Auteur();
                $auteur->setNom($nom);
                $this->em->persist($auteur);
            }

            $lien = new LienAuteurLivre();
            $lien->setLivre($livre);
            $lien->setAuteur($auteur);
            $this->em->persist($lien);
        }
    }

    /**
     * Crée le LienUserLivre avec dateAchat, prixAchat, commentaire
     */
    private function createLienUser(Livre $livre, array $data = []): void
    {
        $lienUser = new LienUserLivre();
        $lienUser->setLivre($livre);
        $lienUser->setUser($this->getUser());

        if (!empty($data['dateAchat'])) {
            $date = $data['dateAchat'] instanceof \DateTimeInterface
                ? $data['dateAchat']
                : new \DateTime($data['dateAchat']);
            $lienUser->setDateAchat($date);
        }
        if (!empty($data['prixAchat'])) {
            $lienUser->setPrixAchat((float) $data['prixAchat']);
        }
        if (!empty($data['monnaieAchat'])) {
            if ($data['monnaieAchat'] instanceof \App\Entity\Monnaie) {
                $lienUser->setMonnaie($data['monnaieAchat']);
            } elseif (is_numeric($data['monnaieAchat'])) {
                $monnaie = $this->em->getRepository(\App\Entity\Monnaie::class)->find($data['monnaieAchat']);
                if ($monnaie) {
                    $lienUser->setMonnaie($monnaie);
                }
            }
        }
        if (!empty($data['commentaire'])) {
            $lienUser->setCommentaire($data['commentaire']);
        }

        $this->em->persist($lienUser);
    }

    private function saveLivre($form, Livre $livre): Response
    {
        // Gérer l'image uploadée ou URL
        $imageFile = $form->get('imageFile')->getData();
        $imageUrl = $form->get('imageUrl')->getData();

        if ($imageFile) {
            // Upload direct depuis le PC
            $filename = uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/covers',
                $filename
            );
            $livre->setImage2($filename);
        } elseif (!empty($imageUrl) && !str_starts_with($imageUrl, '/uploads/')) {
            // URL externe → télécharger l'image
            $this->handleImage($livre, ['imageUrl' => $imageUrl]);
        }

        $this->em->persist($livre);

        // Gérer les auteurs
        $this->handleAuteurs($livre, $form->get('auteurs')->getData());

        // Lier le livre à l'utilisateur courant avec les infos d'acquisition
        $this->createLienUser($livre, [
            'dateAchat' => $form->get('dateAchat')->getData(),
            'prixAchat' => $form->get('prixAchat')->getData(),
            'monnaieAchat' => $form->get('monnaieAchat')->getData(),
            'commentaire' => $form->get('commentaire')->getData(),
        ]);

        $this->em->flush();

        $this->addFlash('warning', 'Le livre "' . $livre->getTitre() . '" a été ajouté avec succès !');

        return $this->redirectToRoute('livreDetail', ['id' => $livre->getId()]);
    }
}
