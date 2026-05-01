<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GameConsoleRepository;

/**
 * Mapping IGDB → codes consoles : uniquement via {@see GameConsoleResolver} et {@see GameConsole::getIgdbPlatformId}.
 */
class IgdbConsoleMappingService
{
    public function __construct(
        private GameConsoleResolver $consoleResolver,
        private GameConsoleRepository $consoleRepo,
    ) {
    }

    public function normalizeSlug(string $nameOrSlug): string
    {
        return strtolower(str_replace([' ', "\t", "\n", "\r"], '', trim($nameOrSlug)));
    }

    public function alphanumericSlug(string $nameOrSlug): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', $nameOrSlug) ?? '');
    }

    public function slugToConsoleCode(string $platformSlug): string
    {
        $n = $this->normalizeSlug($platformSlug);
        $code = $this->consoleResolver->resolveConsoleCode($n);
        if ($code !== null) {
            return $code;
        }
        $n2 = $this->alphanumericSlug($platformSlug);
        if ($n2 !== '') {
            $code = $this->consoleResolver->resolveConsoleCode($n2);
            if ($code !== null) {
                return $code;
            }
        }

        return strlen($n) > 0 ? ucfirst($n) : 'PC';
    }

    /**
     * Filtre recherche API : consoles actives ayant un id plateforme IGDB renseigné en base.
     *
     * @return list<array{igdbId: int, label: string, code: string}>
     */
    public function getSearchFilterChoices(): array
    {
        $out = [];
        foreach ($this->consoleRepo->findWithIgdbPlatformForApiFilter() as $c) {
            $id = $c->getIgdbPlatformId();
            if ($id !== null) {
                $out[] = [
                    'igdbId' => $id,
                    'label' => $c->getNom() ?? $c->getCode(),
                    'code' => $c->getCode(),
                ];
            }
        }

        return $out;
    }
}
