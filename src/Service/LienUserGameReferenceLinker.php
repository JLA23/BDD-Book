<?php

namespace App\Service;

use App\Entity\LienUserGame;
use App\Repository\GameStoreRepository;
use App\Repository\GameTypeEditionRepository;

/**
 * Remplit console_id / type_edition_id / store_id à partir des valeurs formulaire (code console, codes/noms catalogue).
 */
class LienUserGameReferenceLinker
{
    public function __construct(
        private GameConsoleResolver $consoleResolver,
        private GameTypeEditionRepository $typeEditionRepository,
        private GameStoreRepository $storeRepository,
    ) {
    }

    public function link(LienUserGame $lien, ?string $consoleRaw, string $typeEditionCode, ?string $storeNom): void
    {
        $lien->setConsoleEntity($this->consoleResolver->resolveFromLibelle($consoleRaw));

        $typeCode = trim($typeEditionCode);
        $lien->setTypeEditionEntity($typeCode !== '' ? $this->typeEditionRepository->findByCode($typeCode) : null);

        if ($typeCode === 'numerique') {
            $nom = $storeNom !== null ? trim($storeNom) : '';
            $lien->setStoreEntity($nom !== '' ? $this->storeRepository->findOneBy(['nom' => $nom]) : null);
        } else {
            $lien->setStoreEntity(null);
        }
    }
}
