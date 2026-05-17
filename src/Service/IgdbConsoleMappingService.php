<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameConsole;
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
     * ID plateforme IGDB pour une console : colonne en base, sinon carte par défaut.
     */
    public function resolveIgdbPlatformIdForConsole(GameConsole $console): ?int
    {
        $fromDb = $console->getIgdbPlatformId();
        if ($fromDb !== null) {
            return $fromDb;
        }

        return IgdbPlatformDefaults::forConsoleCode($console->getCode());
    }

    /**
     * Valeur envoyée par le filtre (id IGDB numérique ou code console).
     */
    public function resolveIgdbPlatformFilterValue(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $fromCode = IgdbPlatformDefaults::forConsoleCode($value);
        if ($fromCode !== null) {
            return $fromCode;
        }

        $console = $this->consoleRepo->findByCode($value);

        return $console !== null ? $this->resolveIgdbPlatformIdForConsole($console) : null;
    }

    /**
     * Filtre recherche API : toutes les consoles actives avec un id IGDB (base ou défaut).
     *
     * @return list<array{igdbId: int, label: string, code: string, fromDefault: bool}>
     */
    public function getSearchFilterChoices(): array
    {
        $out = [];
        foreach ($this->consoleRepo->findAllOrdered() as $c) {
            $fromDb = $c->getIgdbPlatformId();
            $id = $this->resolveIgdbPlatformIdForConsole($c);
            if ($id === null) {
                continue;
            }
            $out[] = [
                'igdbId' => $id,
                'label' => $c->getNom() ?? $c->getCode(),
                'code' => $c->getCode(),
                'fromDefault' => $fromDb === null,
            ];
        }

        return $out;
    }
}
