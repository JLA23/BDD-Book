<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GameConsole - Consoles / Plateformes de jeux vidéo
 *
 * @ORM\Table(name="game_console")
 * @ORM\Entity(repositoryClass="App\Repository\GameConsoleRepository")
 */
class GameConsole
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string Code court (PS5, XSX, Switch, PC, etc.)
     *
     * @ORM\Column(name="code", type="string", length=30, unique=true)
     */
    private $code;

    /**
     * @var string Nom complet affiché (PlayStation 5, Xbox Series X, etc.)
     *
     * @ORM\Column(name="nom", type="string", length=100)
     */
    private $nom;

    /**
     * @var string|null Classe CSS FontAwesome de l'icône (ex: fab fa-playstation)
     *
     * @ORM\Column(name="icone", type="string", length=100, nullable=true)
     */
    private $icone;

    /**
     * @var string|null Couleur de fond du badge (ex: #003791)
     *
     * @ORM\Column(name="couleur", type="string", length=20, nullable=true)
     */
    private $couleur;

    /**
     * @var int Position pour le tri dans les listes
     *
     * @ORM\Column(name="position", type="integer", options={"default": 0})
     */
    private $position = 0;

    /**
     * @var bool
     *
     * @ORM\Column(name="actif", type="boolean", options={"default": true})
     */
    private $actif = true;

    /**
     * Identifiant plateforme IGDB (filtre API recherche). Nullable si hors périmètre API.
     *
     * @ORM\Column(name="igdb_platform_id", type="integer", nullable=true, unique=true)
     */
    private ?int $igdbPlatformId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(?string $icone): self
    {
        $this->icone = $icone;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): self
    {
        $this->couleur = $couleur;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getIgdbPlatformId(): ?int
    {
        return $this->igdbPlatformId;
    }

    public function setIgdbPlatformId(?int $igdbPlatformId): self
    {
        $this->igdbPlatformId = $igdbPlatformId;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? $this->code ?? '';
    }
}
