<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Libellé brut (formulaires, imports, anciennes données) → console canonique (game_console).
 *
 * @ORM\Table(
 *     name="game_console_alias",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="uniq_game_console_alias_libelle", columns={"libelle"})}
 * )
 * @ORM\Entity(repositoryClass="App\Repository\GameConsoleAliasRepository")
 */
class GameConsoleAlias
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id = null;

    /**
     * Texte saisi en base ou dans un import (ex: « PlayStation 5 », « ps5 »).
     *
     * @ORM\Column(name="libelle", type="string", length=150)
     */
    private string $libelle = '';

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GameConsole")
     * @ORM\JoinColumn(name="console_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private GameConsole $console;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getConsole(): GameConsole
    {
        return $this->console;
    }

    public function setConsole(GameConsole $console): self
    {
        $this->console = $console;
        return $this;
    }
}
