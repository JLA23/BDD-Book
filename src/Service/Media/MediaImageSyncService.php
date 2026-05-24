<?php

namespace App\Service\Media;

use App\Entity\BrickImage;
use App\Entity\BrickSet;
use App\Entity\Dvd;
use App\Entity\DvdUserCollection;
use App\Entity\Game;
use App\Entity\GameImage;
use App\Entity\KioskCollec;
use App\Entity\KioskNum;
use App\Entity\LienUserGame;
use App\Entity\Livre;
use App\Entity\Musique;
use App\Entity\MusiqueUserCollection;

/**
 * Duplique les images distantes (ou blobs kiosque) vers public/uploads/{type}/.
 * Les URL sources en base sont conservées.
 */
class MediaImageSyncService
{
    public function __construct(
        private ImageStorageService $imageStorage,
    ) {
    }

    public function syncLivreCover(Livre $livre): void
    {
        if ($livre->getId() === null) {
            return;
        }

        $source = null;
        if ($livre->getImage2()) {
            $source = $this->imageStorage->resolveBookLegacyPath($livre->getImage2());
            if ($source === null && $this->imageStorage->isRemoteUrl($livre->getImage2())) {
                $source = $livre->getImage2();
            }
        }

        if ($source !== null) {
            $path = $this->imageStorage->mirrorToStorage(
                $source,
                ImageMediaType::BOOK,
                'livre-' . $livre->getId(),
                $livre->getStoredPath()
            );
            if ($path) {
                $livre->setStoredPath($path);
            }

            return;
        }

        $binary = $this->readBlob($livre->getImage());
        if ($binary !== null) {
            $path = $this->imageStorage->storeBinary(
                $binary,
                ImageMediaType::BOOK,
                'livre-' . $livre->getId()
            );
            if ($path) {
                $livre->setStoredPath($path);
            }
        }
    }

    public function syncBrickImage(BrickImage $image): void
    {
        if ($image->getId() === null) {
            return;
        }

        $url = $image->getUrl();
        if ($url && $this->imageStorage->isRemoteUrl($url)) {
            $existing = $image->getFilename()
                ? '/uploads/' . ImageMediaType::BRICK . '/' . $image->getFilename()
                : null;
            $path = $this->imageStorage->mirrorToStorage(
                $url,
                ImageMediaType::BRICK,
                'brick-' . $image->getId(),
                $existing
            );
            if ($path && preg_match('#/([^/]+)$#', $path, $m)) {
                $image->setFilename($m[1]);
            }

            return;
        }

        if ($image->getFilename()) {
            $local = '/uploads/' . ImageMediaType::BRICK . '/' . $image->getFilename();
            if ($this->imageStorage->fileExistsForWebPath($local)) {
                return;
            }
        }
    }

    public function syncBrickSetImages(BrickSet $set): void
    {
        foreach ($set->getImages() as $image) {
            $this->syncBrickImage($image);
        }
    }

    public function syncGameGalleryImage(GameImage $image): void
    {
        if ($image->getId() === null) {
            return;
        }

        $url = $image->getUrl();
        if ($url && $this->imageStorage->isRemoteUrl($url)) {
            $existing = $image->getFilename()
                ? '/uploads/' . ImageMediaType::GAME_GALLERY . '/' . $image->getFilename()
                : null;
            $path = $this->imageStorage->mirrorToStorage(
                $url,
                ImageMediaType::GAME_GALLERY,
                'game-img-' . $image->getId(),
                $existing
            );
            if ($path && preg_match('#/([^/]+)$#', $path, $m)) {
                $image->setFilename($m[1]);
            }
        }
    }

    public function syncGameGallery(Game $game): void
    {
        foreach ($game->getImages() as $image) {
            $this->syncGameGalleryImage($image);
        }
    }

    public function syncDvd(Dvd $dvd, ?DvdUserCollection $lien = null): void
    {
        $this->syncDvdCover($dvd);
        if ($lien !== null) {
            $this->syncDvdUserImage($lien);
        }
    }

    public function syncMusique(Musique $musique, ?MusiqueUserCollection $lien = null): void
    {
        $this->syncMusiqueCover($musique);
        if ($lien !== null) {
            $this->syncMusiqueUserImage($lien);
        }
    }

    public function syncGame(Game $game, ?LienUserGame $lien = null): void
    {
        $this->syncGameCover($game);
        if ($lien !== null) {
            $this->syncGameUserImage($lien);
        }
        $this->syncGameGallery($game);
    }

    public function syncDvdCover(Dvd $dvd): void
    {
        $this->mirrorCoverUrl(
            $dvd->getCoverUrl(),
            ImageMediaType::DVD,
            'dvd-' . $dvd->getId(),
            fn (?string $p) => $dvd->setStoredPath($p),
            $dvd->getStoredPath()
        );
    }

    public function syncMusiqueCover(Musique $musique): void
    {
        $this->mirrorCoverUrl(
            $musique->getCoverUrl(),
            ImageMediaType::MUSIQUE,
            'musique-' . $musique->getId(),
            fn (?string $p) => $musique->setStoredPath($p),
            $musique->getStoredPath()
        );
    }

    public function syncGameCover(Game $game): void
    {
        $this->mirrorCoverUrl(
            $game->getCoverUrl(),
            ImageMediaType::GAME,
            'game-' . $game->getId(),
            fn (?string $p) => $game->setStoredPath($p),
            $game->getStoredPath()
        );
    }

    public function syncDvdUserImage(DvdUserCollection $lien): void
    {
        $this->mirrorUserImage($lien->getImagePerso(), ImageMediaType::DVD_USER, 'dvd-user-' . $lien->getId(), $lien);
    }

    public function syncMusiqueUserImage(MusiqueUserCollection $lien): void
    {
        $this->mirrorUserImage($lien->getImagePerso(), ImageMediaType::MUSIQUE_USER, 'musique-user-' . $lien->getId(), $lien);
    }

    public function syncGameUserImage(LienUserGame $lien): void
    {
        $this->mirrorUserImage($lien->getImagePerso(), ImageMediaType::GAME_USER, 'game-user-' . $lien->getId(), $lien);
    }

    public function syncKioskCollecImage(KioskCollec $magazine, mixed $binary = null): void
    {
        $binary = $binary ?? $this->readBlob($magazine->getImage());
        if ($binary === null) {
            return;
        }

        $path = $this->imageStorage->storeBinary(
            $binary,
            ImageMediaType::MAGAZINE_COLLECTION,
            'magazine-' . $magazine->getId()
        );

        if ($path) {
            $magazine->setStoredPath($path);
        }
    }

    public function syncKioskNumCover(KioskNum $numero, mixed $binary = null): void
    {
        $binary = $binary ?? $this->readBlob($numero->getCouverture());
        if ($binary === null) {
            return;
        }

        $path = $this->imageStorage->storeBinary(
            $binary,
            ImageMediaType::MAGAZINE_NUMERO,
            'numero-' . $numero->getId()
        );

        if ($path) {
            $numero->setStoredPath($path);
        }
    }

    /**
     * @param callable(?string): void $setter
     */
    private function mirrorCoverUrl(
        ?string $coverUrl,
        string $mediaType,
        string $basename,
        callable $setter,
        ?string $existingStored
    ): void {
        if ($coverUrl === null || $coverUrl === '') {
            return;
        }

        $source = $coverUrl;
        if (!$this->imageStorage->isRemoteUrl($coverUrl) && !$this->imageStorage->isLocalWebPath($coverUrl)) {
            $source = '/uploads/' . $mediaType . '/' . ltrim($coverUrl, '/');
        }

        if ($this->imageStorage->isLocalWebPath($coverUrl) && !$existingStored) {
            $setter($coverUrl);

            return;
        }

        if (!$this->imageStorage->isRemoteUrl($source)) {
            if ($this->imageStorage->isLocalWebPath($source)) {
                $setter($source);
            }

            return;
        }

        $path = $this->imageStorage->mirrorToStorage($source, $mediaType, $basename, $existingStored);
        if ($path) {
            $setter($path);
        }
    }

    /**
     * @param object{getStoredPath(): ?string, setStoredPath(?string): void} $entity
     */
    private function mirrorUserImage(?string $imagePerso, string $mediaType, string $basename, object $entity): void
    {
        if ($imagePerso === null || $imagePerso === '') {
            return;
        }

        $existing = method_exists($entity, 'getStoredPath') ? $entity->getStoredPath() : null;

        if (filter_var($imagePerso, FILTER_VALIDATE_URL)) {
            $path = $this->imageStorage->mirrorToStorage($imagePerso, $mediaType, $basename, $existing);
            if ($path) {
                $entity->setStoredPath($path);
            }

            return;
        }

        $local = '/uploads/' . $mediaType . '/' . ltrim($imagePerso, '/');
        if ($this->imageStorage->fileExistsForWebPath($local)) {
            $entity->setStoredPath($local);
        }
    }

    private function readBlob(mixed $blob): ?string
    {
        if ($blob === null) {
            return null;
        }
        if (is_resource($blob)) {
            rewind($blob);

            return stream_get_contents($blob) ?: null;
        }

        return is_string($blob) && $blob !== '' ? $blob : null;
    }
}
