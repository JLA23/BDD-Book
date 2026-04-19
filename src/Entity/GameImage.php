<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GameImage - Images de jeux vidéo
 *
 * @ORM\Table(name="game_image")
 * @ORM\Entity(repositoryClass="App\Repository\GameImageRepository")
 */
class GameImage
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
     * @var string|null URL externe de l'image
     *
     * @ORM\Column(name="url", type="string", length=500, nullable=true)
     */
    private $url;

    /**
     * @var string|null Nom du fichier uploadé
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    private $filename;

    /**
     * @var int Position pour l'ordre d'affichage
     *
     * @ORM\Column(name="position", type="integer")
     */
    private $position = 0;

    /**
     * @var string Source de l'image (API, Upload, URL)
     *
     * @ORM\Column(name="source", type="string", length=50)
     */
    private $source = 'Upload';

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Game", inversedBy="images")
     * @ORM\JoinColumn(name="game_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $game;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
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

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;
        return $this;
    }

    public function getDisplayUrl(): string
    {
        if ($this->url) {
            return $this->url;
        }
        if ($this->filename) {
            return '/uploads/game/' . $this->filename;
        }
        return '/images/no-image.png';
    }
}
