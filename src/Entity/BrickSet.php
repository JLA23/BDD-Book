<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * BrickSet - Set de briques
 *
 * @ORM\Table(name="brick_set")
 * @ORM\Entity(repositoryClass="App\Repository\BrickSetRepository")
 */
class BrickSet
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
     * @ORM\Column(name="nom", type="string", length=255)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="reference", type="string", length=50, unique=true)
     */
    private $reference;

    /**
     * @var float|null
     *
     * @ORM\Column(name="prix", type="float", nullable=true)
     */
    private $prix;

    /**
     * @var int|null
     *
     * @ORM\Column(name="annee", type="integer", nullable=true)
     */
    private $annee;

    /**
     * @var int|null
     *
     * @ORM\Column(name="nb_pieces", type="integer", nullable=true)
     */
    private $nbPieces;

    /**
     * @var string|null
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BrickMarque", inversedBy="sets")
     * @ORM\JoinColumn(name="marque_id", referencedColumnName="id", nullable=true)
     */
    private $marque;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BrickCollection", inversedBy="sets")
     * @ORM\JoinColumn(name="collection_id", referencedColumnName="id", nullable=true)
     */
    private $collection;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BrickImage", mappedBy="brickSet", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"position" = "ASC"})
     */
    private $images;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LienUserBrickSet", mappedBy="brickSet", cascade={"remove"})
     */
    private $listeUser;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private $updatedAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->listeUser = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): self
    {
        $this->prix = $prix;
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

    public function getNbPieces(): ?int
    {
        return $this->nbPieces;
    }

    public function setNbPieces(?int $nbPieces): self
    {
        $this->nbPieces = $nbPieces;
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

    public function getMarque(): ?BrickMarque
    {
        return $this->marque;
    }

    public function setMarque(?BrickMarque $marque): self
    {
        $this->marque = $marque;
        return $this;
    }

    public function getCollection(): ?BrickCollection
    {
        return $this->collection;
    }

    public function setCollection(?BrickCollection $collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @return Collection|BrickImage[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(BrickImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setBrickSet($this);
        }
        return $this;
    }

    public function removeImage(BrickImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getBrickSet() === $this) {
                $image->setBrickSet(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|LienUserBrickSet[]
     */
    public function getListeUser(): Collection
    {
        return $this->listeUser;
    }

    public function getMainImage(): ?BrickImage
    {
        if ($this->images->isEmpty()) {
            return null;
        }
        return $this->images->first();
    }

    public function getMainImageUrl(): string
    {
        $mainImage = $this->getMainImage();
        if ($mainImage) {
            return $mainImage->getDisplayUrl();
        }
        return '/images/brick-default.svg';
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
