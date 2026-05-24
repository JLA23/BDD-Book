<?php

namespace App\Service;

use App\Entity\GameConsole;
use App\Repository\GameConsoleAliasRepository;
use App\Repository\GameConsoleRepository;

/**
 * Résout une chaîne utilisateur (code, nom affiché ou alias) vers GameConsole.
 */
class GameConsoleResolver
{
    public function __construct(
        private GameConsoleAliasRepository $aliasRepository,
        private GameConsoleRepository $consoleRepository,
    ) {
    }

    public function resolveFromLibelle(?string $raw): ?GameConsole
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $alias = $this->aliasRepository->findOneByLibelleInsensitive($raw);
        if ($alias !== null) {
            return $alias->getConsole();
        }

        $byCode = $this->consoleRepository->findByCode($raw);
        if ($byCode !== null) {
            return $byCode;
        }

        return $this->consoleRepository->findOneBy(['nom' => $raw]);
    }

    public function resolveConsoleCode(?string $raw): ?string
    {
        return $this->resolveFromLibelle($raw)?->getCode();
    }
}
