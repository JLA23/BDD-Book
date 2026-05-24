<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\ConsoleSlugAliasSeedData;
use App\Entity\GameConsoleAlias;
use App\Repository\GameConsoleAliasRepository;
use App\Repository\GameConsoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Peuple game_console_alias à partir des données de seed (idempotent).
 */
class ConsoleSlugAliasSeeder
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameConsoleRepository $consoleRepo,
        private GameConsoleAliasRepository $aliasRepo,
    ) {
    }

    /**
     * Insère les alias manquants uniquement (compare libellé insensible à la casse).
     *
     * @return int nombre d’alias créés
     */
    public function seedMissingAliases(?SymfonyStyle $io = null): int
    {
        $added = 0;
        foreach (ConsoleSlugAliasSeedData::aliasesGroupedByConsoleCode() as $code => $libelles) {
            $console = $this->consoleRepo->findByCode($code);
            if (!$console) {
                $io?->warning("Console « {$code} » absente : alias ignorés pour ce code.");
                continue;
            }
            foreach ($libelles as $libelle) {
                $libelle = trim((string) $libelle);
                if ($libelle === '') {
                    continue;
                }
                if ($this->aliasRepo->findOneByLibelleInsensitive($libelle)) {
                    continue;
                }
                $alias = new GameConsoleAlias();
                $alias->setLibelle($libelle)->setConsole($console);
                $this->em->persist($alias);
                ++$added;
            }
        }

        return $added;
    }
}
