<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * BrickCollection - Thèmes/Collections (Star Wars, Technic, City, etc.)
 *
 * @ORM\Table(name="brick_collection")
 * @ORM\Entity(repositoryClass="App\Repository\BrickCollectionRepository")
 */
class BrickCollection
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
     * @var string|null
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BrickSet", mappedBy="collection")
     */
    private $sets;

    public function __construct()
    {
        $this->sets = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection|BrickSet[]
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function addSet(BrickSet $set): self
    {
        if (!$this->sets->contains($set)) {
            $this->sets[] = $set;
            $set->setCollection($this);
        }
        return $this;
    }

    public function removeSet(BrickSet $set): self
    {
        if ($this->sets->removeElement($set)) {
            if ($set->getCollection() === $this) {
                $set->setCollection(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
