<?php

namespace App\Controller;

use App\Entity\GameConsole;
use App\Entity\GameConsoleAlias;
use App\Entity\GameStore;
use App\Repository\GameConsoleAliasRepository;
use App\Repository\GameConsoleRepository;
use App\Repository\GameStoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/games')]
class AdminGameController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('', name: 'admin_game_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/game/index.html.twig');
    }

    // ========== CONSOLES ==========

    #[Route('/consoles', name: 'admin_game_consoles')]
    public function consoles(GameConsoleRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/game/consoles.html.twig', [
            'consoles' => $repo->findBy([], ['position' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/console/nouveau', name: 'admin_game_console_new')]
    public function consoleNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $console = new GameConsole();
            $console->setCode($request->request->get('code'));
            $console->setNom($request->request->get('nom'));
            $console->setIcone($request->request->get('icone') ?: null);
            $console->setCouleur($request->request->get('couleur') ?: null);
            $console->setPosition((int) $request->request->get('position', 0));
            $console->setActif($request->request->has('actif'));
            $igdb = $request->request->get('igdb_platform_id');
            $console->setIgdbPlatformId($igdb !== null && $igdb !== '' ? (int) $igdb : null);

            $this->em->persist($console);
            $this->em->flush();

            $this->addFlash('success', 'Console "' . $console->getNom() . '" créée');
            return $this->redirectToRoute('admin_game_consoles');
        }

        return $this->render('admin/game/console_form.html.twig', [
            'console' => null,
        ]);
    }

    #[Route('/console/{id}/modifier', name: 'admin_game_console_edit', requirements: ['id' => '\d+'])]
    public function consoleEdit(int $id, Request $request, GameConsoleRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $console = $repo->find($id);
        if (!$console) {
            throw $this->createNotFoundException('Console non trouvée');
        }

        if ($request->isMethod('POST')) {
            $console->setCode($request->request->get('code'));
            $console->setNom($request->request->get('nom'));
            $console->setIcone($request->request->get('icone') ?: null);
            $console->setCouleur($request->request->get('couleur') ?: null);
            $console->setPosition((int) $request->request->get('position', 0));
            $console->setActif($request->request->has('actif'));
            $igdb = $request->request->get('igdb_platform_id');
            $console->setIgdbPlatformId($igdb !== null && $igdb !== '' ? (int) $igdb : null);

            $this->em->flush();

            $this->addFlash('success', 'Console "' . $console->getNom() . '" modifiée');
            return $this->redirectToRoute('admin_game_consoles');
        }

        return $this->render('admin/game/console_form.html.twig', [
            'console' => $console,
        ]);
    }

    #[Route('/console/{id}/supprimer', name: 'admin_game_console_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function consoleDelete(int $id, Request $request, GameConsoleRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $console = $repo->find($id);
        if ($console && $this->isCsrfTokenValid('delete-console-' . $id, $request->request->get('_token'))) {
            $this->em->remove($console);
            $this->em->flush();
            $this->addFlash('success', 'Console supprimée');
        }

        return $this->redirectToRoute('admin_game_consoles');
    }

    #[Route('/console-aliases', name: 'admin_game_console_aliases')]
    public function consoleAliases(Request $request, GameConsoleAliasRepository $aliasRepo, GameConsoleRepository $consoleRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $libelle = trim((string) $request->request->get('libelle'));
            $consoleId = (int) $request->request->get('console_id');

            if ($libelle === '' || $consoleId < 1) {
                $this->addFlash('danger', 'Libellé et console sont obligatoires');
            } else {
                $console = $consoleRepo->find($consoleId);
                if (!$console) {
                    $this->addFlash('danger', 'Console introuvable');
                } elseif ($aliasRepo->findOneByLibelleInsensitive($libelle)) {
                    $this->addFlash('danger', 'Ce libellé existe déjà dans le mapping');
                } else {
                    $alias = new GameConsoleAlias();
                    $alias->setLibelle($libelle)->setConsole($console);
                    $this->em->persist($alias);
                    $this->em->flush();
                    $this->addFlash('success', 'Mapping ajouté : « ' . $libelle . ' » → ' . $console->getNom());
                }
            }

            return $this->redirectToRoute('admin_game_console_aliases');
        }

        return $this->render('admin/game/console_aliases.html.twig', [
            'aliases' => $aliasRepo->createQueryBuilder('a')
                ->join('a.console', 'c')->addSelect('c')
                ->orderBy('c.position', 'ASC')
                ->addOrderBy('a.libelle', 'ASC')
                ->getQuery()
                ->getResult(),
            'consoles' => $consoleRepo->findBy([], ['position' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/console-alias/{id}/supprimer', name: 'admin_game_console_alias_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function consoleAliasDelete(int $id, Request $request, GameConsoleAliasRepository $aliasRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $alias = $aliasRepo->find($id);
        if ($alias && $this->isCsrfTokenValid('delete-console-alias-' . $id, $request->request->get('_token'))) {
            $this->em->remove($alias);
            $this->em->flush();
            $this->addFlash('success', 'Mapping supprimé');
        }

        return $this->redirectToRoute('admin_game_console_aliases');
    }

    // ========== STORES ==========

    #[Route('/stores', name: 'admin_game_stores')]
    public function stores(GameStoreRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/game/stores.html.twig', [
            'stores' => $repo->findBy([], ['position' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/store/nouveau', name: 'admin_game_store_new')]
    public function storeNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $store = new GameStore();
            $store->setNom($request->request->get('nom'));
            $store->setIcone($request->request->get('icone') ?: null);
            $store->setPosition((int) $request->request->get('position', 0));
            $store->setActif($request->request->has('actif'));

            $this->em->persist($store);
            $this->em->flush();

            $this->addFlash('success', 'Store "' . $store->getNom() . '" créé');
            return $this->redirectToRoute('admin_game_stores');
        }

        return $this->render('admin/game/store_form.html.twig', [
            'store' => null,
        ]);
    }

    #[Route('/store/{id}/modifier', name: 'admin_game_store_edit', requirements: ['id' => '\d+'])]
    public function storeEdit(int $id, Request $request, GameStoreRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $store = $repo->find($id);
        if (!$store) {
            throw $this->createNotFoundException('Store non trouvé');
        }

        if ($request->isMethod('POST')) {
            $store->setNom($request->request->get('nom'));
            $store->setIcone($request->request->get('icone') ?: null);
            $store->setPosition((int) $request->request->get('position', 0));
            $store->setActif($request->request->has('actif'));

            $this->em->flush();

            $this->addFlash('success', 'Store "' . $store->getNom() . '" modifié');
            return $this->redirectToRoute('admin_game_stores');
        }

        return $this->render('admin/game/store_form.html.twig', [
            'store' => $store,
        ]);
    }
}
