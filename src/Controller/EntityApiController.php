<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Collection;
use App\Entity\Edition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class EntityApiController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/category', name: 'api_category_create', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $nom = trim($data['nom'] ?? '');

        if (empty($nom)) {
            return new JsonResponse(['success' => false, 'error' => 'Le nom est obligatoire'], 400);
        }

        // Vérifier si la catégorie existe déjà
        $existing = $this->em->getRepository(Category::class)->findOneBy(['nom' => $nom]);
        if ($existing) {
            return new JsonResponse([
                'success' => true,
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
                'message' => 'Cette catégorie existe déjà'
            ]);
        }

        $category = new Category();
        $category->setNom($nom);
        $this->em->persist($category);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $category->getId(),
            'nom' => $category->getNom(),
            'message' => 'Catégorie créée avec succès'
        ]);
    }

    #[Route('/collection', name: 'api_collection_create', methods: ['POST'])]
    public function createCollection(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $nom = trim($data['nom'] ?? '');

        if (empty($nom)) {
            return new JsonResponse(['success' => false, 'error' => 'Le nom est obligatoire'], 400);
        }

        // Vérifier si la collection existe déjà
        $existing = $this->em->getRepository(Collection::class)->findOneBy(['nom' => $nom]);
        if ($existing) {
            return new JsonResponse([
                'success' => true,
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
                'message' => 'Cette collection existe déjà'
            ]);
        }

        $collection = new Collection();
        $collection->setNom($nom);
        $this->em->persist($collection);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $collection->getId(),
            'nom' => $collection->getNom(),
            'message' => 'Collection créée avec succès'
        ]);
    }

    #[Route('/edition', name: 'api_edition_create', methods: ['POST'])]
    public function createEdition(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $data = json_decode($request->getContent(), true);
        $nom = trim($data['nom'] ?? '');

        if (empty($nom)) {
            return new JsonResponse(['success' => false, 'error' => 'Le nom est obligatoire'], 400);
        }

        // Vérifier si l'éditeur existe déjà
        $existing = $this->em->getRepository(Edition::class)->findOneBy(['nom' => $nom]);
        if ($existing) {
            return new JsonResponse([
                'success' => true,
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
                'message' => 'Cet éditeur existe déjà'
            ]);
        }

        $edition = new Edition();
        $edition->setNom($nom);
        $this->em->persist($edition);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $edition->getId(),
            'nom' => $edition->getNom(),
            'message' => 'Éditeur créé avec succès'
        ]);
    }
}
