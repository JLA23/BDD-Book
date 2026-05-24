<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait StoredImagePathTrait
{
    /**
     * Chemin web local (/uploads/{type}/fichier) — l'URL source reste dans le champ dédié.
     *
     * @ORM\Column(name="stored_path", type="string", length=500, nullable=true)
     */
    private ?string $storedPath = null;

    public function getStoredPath(): ?string
    {
        return $this->storedPath;
    }

    public function setStoredPath(?string $storedPath): self
    {
        $this->storedPath = $storedPath;

        return $this;
    }
}
