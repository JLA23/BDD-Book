<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GameStore - Magasins numériques (Steam, Epic, PSN, etc.)
 *
 * @ORM\Table(name="game_store")
 * @ORM\Entity(repositoryClass="App\Repository\GameStoreRepository")
 */
class GameStore
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
     * @var string Nom du store (Steam, Epic Games, PlayStation Store, etc.)
     *
     * @ORM\Column(name="nom", type="string", length=100, unique=true)
     */
    private $nom;

    /**
     * @var string|null Icône FontAwesome (ex: fab fa-steam)
     *
     * @ORM\Column(name="icone", type="string", length=100, nullable=true)
     */
    private $icone;

    /**
     * @var int
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

    public function getId(): ?int
    {
        return $this->id;
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

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
