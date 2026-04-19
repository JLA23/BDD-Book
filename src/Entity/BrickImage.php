<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BrickImage - Images d'un set
 *
 * @ORM\Table(name="brick_image")
 * @ORM\Entity(repositoryClass="App\Repository\BrickImageRepository")
 */
class BrickImage
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
     * @var string|null
     *
     * @ORM\Column(name="url", type="string", length=500, nullable=true)
     */
    private $url;

    /**
     * @var string|null
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    private $filename;

    /**
     * @var int
     *
     * @ORM\Column(name="position", type="integer")
     */
    private $position = 0;

    /**
     * @var string|null
     *
     * @ORM\Column(name="source", type="string", length=100, nullable=true)
     */
    private $source;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BrickSet", inversedBy="images")
     * @ORM\JoinColumn(name="brick_set_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $brickSet;

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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getBrickSet(): ?BrickSet
    {
        return $this->brickSet;
    }

    public function setBrickSet(?BrickSet $brickSet): self
    {
        $this->brickSet = $brickSet;
        return $this;
    }

    public function getDisplayUrl(): string
    {
        if ($this->filename) {
            return '/uploads/brick/' . $this->filename;
        }
        return $this->url ?? '/images/brick-default.svg';
    }
}
