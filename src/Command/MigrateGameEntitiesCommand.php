<?php

namespace App\Command;

use App\Entity\GameConsole;
use App\Entity\GameTypeEdition;
use App\Entity\GameStore;
use App\Repository\GameConsoleRepository;
use App\Repository\GameTypeEditionRepository;
use App\Repository\GameStoreRepository;
use App\Service\ConsoleSlugAliasSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-game-entities',
    description: 'Migre les données string (console, type_edition, store) de lien_user_game vers les nouvelles entités'
)]
class MigrateGameEntitiesCommand extends Command
{
    private EntityManagerInterface $em;
    private GameConsoleRepository $consoleRepo;
    private GameTypeEditionRepository $typeEditionRepo;
    private GameStoreRepository $storeRepo;
    private ConsoleSlugAliasSeeder $slugAliasSeeder;

    public function __construct(
        EntityManagerInterface $em,
        GameConsoleRepository $consoleRepo,
        GameTypeEditionRepository $typeEditionRepo,
        GameStoreRepository $storeRepo,
        ConsoleSlugAliasSeeder $slugAliasSeeder
    ) {
        parent::__construct();
        $this->em = $em;
        $this->consoleRepo = $consoleRepo;
        $this->typeEditionRepo = $typeEditionRepo;
        $this->storeRepo = $storeRepo;
        $this->slugAliasSeeder = $slugAliasSeeder;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        // === Étape 1: Peupler les tables de référence si vides ===
        $io->section('Vérification des tables de référence');

        $this->seedConsoles($io);
        $this->seedConsoleAliases($io);
        $this->seedTypeEditions($io);
        $this->seedStores($io);

        $this->em->flush();

        // === Étape 2: Migrer les données existantes ===
        $io->section('Migration des données de lien_user_game');

        // Consoles via table de mapping (alias)
        try {
            $viaAlias = $conn->executeStatement(
                'UPDATE lien_user_game lug
                 INNER JOIN game_console_alias gca ON LOWER(TRIM(gca.libelle)) = LOWER(TRIM(lug.console))
                 SET lug.console_id = gca.console_id
                 WHERE lug.console IS NOT NULL AND TRIM(lug.console) <> \'\' AND lug.console_id IS NULL'
            );
            $io->text("  Consoles résolues via game_console_alias : {$viaAlias} ligne(s)");
        } catch (\Throwable $e) {
            $io->warning('  Impossible d\'appliquer le mapping alias (table absente ?) : ' . $e->getMessage());
        }

        // Consoles
        $result = $conn->executeQuery(
            "SELECT DISTINCT console FROM lien_user_game WHERE console IS NOT NULL AND console != '' AND console_id IS NULL"
        )->fetchAllAssociative();

        $migratedConsoles = 0;
        foreach ($result as $row) {
            $code = $row['console'];
            $console = $this->consoleRepo->findByCode($code);
            if ($console) {
                $count = $conn->executeStatement(
                    "UPDATE lien_user_game SET console_id = ? WHERE console = ? AND console_id IS NULL",
                    [$console->getId(), $code]
                );
                $migratedConsoles += $count;
                $io->text("  Console '{$code}' -> ID {$console->getId()} ({$count} lignes)");
            } else {
                $io->warning("  Console '{$code}' non trouvée dans game_console !");
            }
        }
        $io->success("Consoles migrées: {$migratedConsoles} lignes");

        // Type édition
        $result = $conn->executeQuery(
            "SELECT DISTINCT type_edition FROM lien_user_game WHERE type_edition IS NOT NULL AND type_edition != '' AND type_edition_id IS NULL"
        )->fetchAllAssociative();

        $migratedTypes = 0;
        foreach ($result as $row) {
            $code = $row['type_edition'];
            $type = $this->typeEditionRepo->findByCode($code);
            if ($type) {
                $count = $conn->executeStatement(
                    "UPDATE lien_user_game SET type_edition_id = ? WHERE type_edition = ? AND type_edition_id IS NULL",
                    [$type->getId(), $code]
                );
                $migratedTypes += $count;
                $io->text("  TypeEdition '{$code}' -> ID {$type->getId()} ({$count} lignes)");
            } else {
                $io->warning("  TypeEdition '{$code}' non trouvé dans game_type_edition !");
            }
        }
        $io->success("Types d'édition migrés: {$migratedTypes} lignes");

        // Stores (éditions numériques pertinentes ; les autres auront store vide)
        $result = $conn->executeQuery(
            "SELECT DISTINCT store FROM lien_user_game WHERE store IS NOT NULL AND store != '' AND store_id IS NULL AND type_edition = 'numerique'"
        )->fetchAllAssociative();

        $migratedStores = 0;
        foreach ($result as $row) {
            $nom = $row['store'];
            $store = $this->storeRepo->findOneBy(['nom' => $nom]);
            if ($store) {
                $count = $conn->executeStatement(
                    "UPDATE lien_user_game SET store_id = ? WHERE store = ? AND store_id IS NULL",
                    [$store->getId(), $nom]
                );
                $migratedStores += $count;
                $io->text("  Store '{$nom}' -> ID {$store->getId()} ({$count} lignes)");
            } else {
                $io->warning("  Store '{$nom}' non trouvé dans game_store !");
            }
        }
        $io->success("Stores migrés: {$migratedStores} lignes");

        $io->success('Migration terminée !');
        return Command::SUCCESS;
    }

    private function seedConsoles(SymfonyStyle $io): void
    {
        if ($this->consoleRepo->count([]) > 0) {
            $io->text('Table game_console déjà peuplée (' . $this->consoleRepo->count([]) . ' entrées)');
            return;
        }

        // code, nom, icône, couleur, position, igdb_platform_id (nullable)
        $consoles = [
            ['PS5',      'PlayStation 5',      'fab fa-playstation', '#003791', 1,  167],
            ['PS4',      'PlayStation 4',      'fab fa-playstation', '#003791', 2,  48],
            ['PS3',      'PlayStation 3',      'fab fa-playstation', '#003791', 3,  16],
            ['PS2',      'PlayStation 2',      'fab fa-playstation', '#003791', 4,  9],
            ['PS1',      'PlayStation',        'fab fa-playstation', '#003791', 5,  7],
            ['PSVita',   'PS Vita',            'fab fa-playstation', '#003791', 6,  46],
            ['PSP',      'PSP',                'fab fa-playstation', '#003791', 7,  38],
            ['XSX',      'Xbox Series X',      'fab fa-xbox',        '#107C10', 10, 169],
            ['XOne',     'Xbox One',           'fab fa-xbox',        '#107C10', 11, 12],
            ['X360',     'Xbox 360',           'fab fa-xbox',        '#107C10', 12, 11],
            ['Xbox',     'Xbox',               'fab fa-xbox',        '#107C10', 13, 1],
            ['Switch',   'Nintendo Switch',    'fas fa-gamepad',     '#E60012', 20, 130],
            ['WiiU',     'Wii U',              'fas fa-gamepad',     '#E60012', 21, 41],
            ['Wii',      'Wii',                'fas fa-gamepad',     '#E60012', 22, 5],
            ['GameCube', 'GameCube',           'fas fa-gamepad',     '#E60012', 23, 21],
            ['N64',      'Nintendo 64',        'fas fa-gamepad',     '#E60012', 24, 4],
            ['3DS',      'Nintendo 3DS',       'fas fa-gamepad',     '#E60012', 25, 18],
            ['DS',       'Nintendo DS',        'fas fa-gamepad',     '#E60012', 26, 20],
            ['GBA',      'Game Boy Advance',   'fas fa-gamepad',     '#E60012', 27, 24],
            ['GB',       'Game Boy',           'fas fa-gamepad',     '#E60012', 28, null],
            ['PC',       'PC',                 'fas fa-desktop',     '#333333', 30, 6],
            ['Mac',      'Mac',                'fab fa-apple',       '#555555', 31, 14],
            ['Linux',    'Linux',              'fab fa-linux',       '#FCC624', 32, 3],
            ['Android',  'Android',            'fab fa-android',     '#3DDC84', 33, 34],
            ['iOS',      'iOS',                'fab fa-apple',       '#555555', 34, 39],
        ];

        foreach ($consoles as $row) {
            [$code, $nom, $icone, $couleur, $position, $igdb] = array_pad($row, 6, null);
            $c = new GameConsole();
            $c->setCode($code)->setNom($nom)->setIcone($icone)->setCouleur($couleur)->setPosition($position);
            if ($igdb !== null) {
                $c->setIgdbPlatformId((int) $igdb);
            }
            $this->em->persist($c);
        }

        $io->text('Table game_console peuplée avec ' . count($consoles) . ' entrées');
    }

    private function seedConsoleAliases(SymfonyStyle $io): void
    {
        $added = $this->slugAliasSeeder->seedMissingAliases($io);
        $io->text('Alias consoles (slug / libellés) : ' . $added . ' nouvelle(s) entrée(s) (doublons ignorés)');
    }

    private function seedTypeEditions(SymfonyStyle $io): void
    {
        if ($this->typeEditionRepo->count([]) > 0) {
            $io->text('Table game_type_edition déjà peuplée');
            return;
        }

        $types = [
            ['physique',  'Physique',  1],
            ['numerique', 'Numérique', 2],
        ];

        foreach ($types as [$code, $nom, $position]) {
            $t = new GameTypeEdition();
            $t->setCode($code)->setNom($nom)->setPosition($position);
            $this->em->persist($t);
        }

        $io->text('Table game_type_edition peuplée');
    }

    private function seedStores(SymfonyStyle $io): void
    {
        if ($this->storeRepo->count([]) > 0) {
            $io->text('Table game_store déjà peuplée');
            return;
        }

        $stores = [
            ['Steam',              'fab fa-steam',       1],
            ['Epic Games',         'fas fa-gamepad',     2],
            ['GOG',                'fas fa-gamepad',     3],
            ['PlayStation Store',  'fab fa-playstation', 4],
            ['Xbox Store',         'fab fa-xbox',        5],
            ['Nintendo eShop',     'fas fa-gamepad',     6],
            ['Ubisoft Connect',    'fas fa-gamepad',     7],
            ['EA App',             'fas fa-gamepad',     8],
            ['Battle.net',         'fas fa-gamepad',     9],
            ['Autre',              'fas fa-store',       99],
        ];

        foreach ($stores as [$nom, $icone, $position]) {
            $s = new GameStore();
            $s->setNom($nom)->setIcone($icone)->setPosition($position);
            $this->em->persist($s);
        }

        $io->text('Table game_store peuplée');
    }
}
