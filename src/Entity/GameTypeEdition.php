<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GameTypeEdition - Types d'édition de jeux (physique, numérique)
 *
 * @ORM\Table(name="game_type_edition")
 * @ORM\Entity(repositoryClass="App\Repository\GameTypeEditionRepository")
 */
class GameTypeEdition
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
     * @var string Code interne (physique, numerique)
     *
     * @ORM\Column(name="code", type="string", length=30, unique=true)
     */
    private $code;

    /**
     * @var string Nom affiché (Physique, Numérique)
     *
     * @ORM\Column(name="nom", type="string", length=50)
     */
    private $nom;

    /**
     * @var int
     *
     * @ORM\Column(name="position", type="integer", options={"default": 0})
     */
    private $position = 0;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? $this->code ?? '';
    }
}
