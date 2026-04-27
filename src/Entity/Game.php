<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Game - Jeux vidéo
 *
 * @ORM\Table(name="game")
 * @ORM\Entity(repositoryClass="App\Repository\GameRepository")
 */
class Game
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
     * @var string
     *
     * @ORM\Column(name="titre", type="string", length=255)
     */
    private $titre;

    /**
     * @var int|null
     *
     * @ORM\Column(name="annee", type="integer", nullable=true)
     */
    private $annee;

    /**
     * @var string|null
     *
     * @ORM\Column(name="editeur", type="string", length=255, nullable=true)
     */
    private $editeur;

    /**
     * @var string|null
     *
     * @ORM\Column(name="developpeur", type="string", length=255, nullable=true)
     */
    private $developpeur;

    /**
     * @var string|null
     *
     * @ORM\Column(name="classification", type="string", length=50, nullable=true)
     */
    private $classification;

    /**
     * @var string|null
     *
     * @ORM\Column(name="genre", type="string", length=100, nullable=true)
     */
    private $genre;

    /**
     * @var string|null
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cover_url", type="string", length=500, nullable=true)
     */
    private $coverUrl;

    /**
     * @var string|null ID externe (IGDB, RAWG, etc.)
     *
     * @ORM\Column(name="external_id", type="string", length=100, nullable=true)
     */
    private $externalId;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GameImage", mappedBy="game", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"position" = "ASC"})
     */
    private $images;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LienUserGame", mappedBy="game", cascade={"remove"})
     */
    private $userLinks;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->userLinks = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(?int $annee): self
    {
        $this->annee = $annee;
        return $this;
    }

    public function getEditeur(): ?string
    {
        return $this->editeur;
    }

    public function setEditeur(?string $editeur): self
    {
        $this->editeur = $editeur;
        return $this;
    }

    public function getDeveloppeur(): ?string
    {
        return $this->developpeur;
    }

    public function setDeveloppeur(?string $developpeur): self
    {
        $this->developpeur = $developpeur;
        return $this;
    }

    public function getClassification(): ?string
    {
        return $this->classification;
    }

    public function setClassification(?string $classification): self
    {
        $this->classification = $classification;
        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): self
    {
        $this->genre = $genre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): self
    {
        $this->coverUrl = $coverUrl;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return Collection|GameImage[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(GameImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setGame($this);
        }
        return $this;
    }

    public function removeImage(GameImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getGame() === $this) {
                $image->setGame(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|LienUserGame[]
     */
    public function getUserLinks(): Collection
    {
        return $this->userLinks;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMainImage(): ?string
    {
        if ($this->coverUrl) {
            return $this->coverUrl;
        }
        foreach ($this->images as $image) {
            return $image->getUrl() ?: '/uploads/game/' . $image->getFilename();
        }
        return null;
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
    }
}
