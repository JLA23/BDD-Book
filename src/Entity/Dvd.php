<?php

namespace App\Entity;

use App\Entity\Trait\StoredImagePathTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Dvd - DVD / Blu-ray
 *
 * @ORM\Table(name="dvd")
 * @ORM\Entity(repositoryClass="App\Repository\DvdRepository")
 */
class Dvd
{
    use StoredImagePathTrait;

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
     * @var string|null film ou serie
     *
     * @ORM\Column(name="type", type="string", length=20, nullable=true)
     */
    private $type;

    /**
     * @var string|null dvd, bluray, bluray4k
     *
     * @ORM\Column(name="format", type="string", length=20, nullable=true)
     */
    private $format;

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
     * @var string|null ID externe (DVDFR)
     *
     * @ORM\Column(name="external_id", type="string", length=100, nullable=true)
     */
    private $externalId;

    /**
     * @var string|null Édition commerciale (collector, steelbook, etc.)
     *
     * @ORM\Column(name="edition", type="string", length=255, nullable=true)
     */
    private $edition;

    /**
     * @var string|null Code-barres EAN-13
     *
     * @ORM\Column(name="ean", type="string", length=20, nullable=true)
     */
    private $ean;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DvdUserCollection", mappedBy="dvd", cascade={"remove"})
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;
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

    public function getEdition(): ?string
    {
        return $this->edition;
    }

    public function setEdition(?string $edition): self
    {
        $this->edition = $edition;
        return $this;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): self
    {
        $this->ean = $ean;
        return $this;
    }

    /**
     * @return Collection|DvdUserCollection[]
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
        if (!empty($this->storedPath)) {
            return $this->storedPath;
        }

        return $this->coverUrl;
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
    }
}
