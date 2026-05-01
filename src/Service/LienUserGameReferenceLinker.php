<?php

namespace App\Service;

use App\Entity\LienUserGame;
use App\Repository\GameStoreRepository;
use App\Repository\GameTypeEditionRepository;

/**
 * Met à jour les associationsManyToOne à partir des champs chaîne conservés sur lien_user_game.
 */
class LienUserGameReferenceLinker
{
    public function __construct(
        private GameConsoleResolver $consoleResolver,
        private GameTypeEditionRepository $typeEditionRepository,
        private GameStoreRepository $storeRepository,
    ) {
    }

    public function link(LienUserGame $lien): void
    {
        $lien->setConsoleEntity($this->consoleResolver->resolveFromLibelle($lien->getConsole()));

        $typeCode = trim((string) $lien->getTypeEdition());
        $lien->setTypeEditionEntity($typeCode !== '' ? $this->typeEditionRepository->findByCode($typeCode) : null);

        if ($lien->isNumerique()) {
            $storeNom = $lien->getStore();
            $storeNom = $storeNom !== null ? trim($storeNom) : '';
            $lien->setStoreEntity($storeNom !== '' ? $this->storeRepository->findOneBy(['nom' => $storeNom]) : null);
        } else {
            $lien->setStoreEntity(null);
        }
    }
}
