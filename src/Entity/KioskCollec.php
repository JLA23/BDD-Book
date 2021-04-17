<?php

namespace App\Entity;

use App\Repository\KioskCollecRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=KIOSKCOLLECRepository::class)
 */
class KioskCollec
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $editeur;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $debpub;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $findeb;

    /**
     * @ORM\Column(type="integer")
     */
    private $nbnum;

    /**
     * @ORM\Column(type="boolean",  options={"default" : true})
     */
    private $statut;

    /**
     * @ORM\Column(type="blob", nullable=true)
     */
    private $image;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="date", nullable=false)
     */
    private $createDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="createUser", referencedColumnName="id", nullable=false)
     */
    private $createUser;

    /**
     * @ORM\Column(type="date", nullable=false)
     */
    private $updateDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="updateUser", referencedColumnName="id", nullable=false)
     */
    private $updateUser;

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

    public function getEditeur(): ?string
    {
        return $this->editeur;
    }

    public function setEditeur(?string $editeur): self
    {
        $this->editeur = $editeur;

        return $this;
    }

    public function getDebpub(): ?\DateTimeInterface
    {
        return $this->debpub;
    }

    public function setDebpub(?\DateTimeInterface $debpub): self
    {
        $this->debpub = $debpub;

        return $this;
    }

    public function getFindeb(): ?\DateTimeInterface
    {
        return $this->findeb;
    }

    public function setFindeb(?\DateTimeInterface $findeb): self
    {
        $this->findeb = $findeb;

        return $this;
    }

    public function getNbnum(): ?int
    {
        return $this->nbnum;
    }

    public function setNbnum(int $nbnum): self
    {
        $this->nbnum = $nbnum;

        return $this;
    }

    public function getStatut(): ?bool
    {
        return $this->statut;
    }

    public function setStatut(bool $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setImage($image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getCreateDate(): ?\DateTimeInterface
    {
        return $this->createDate;
    }

    public function setCreateDate(\DateTimeInterface $createDate): self
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getCreateUser()
    {
        return $this->createUser;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return KioskCollec
     */
    public function setCreateUser($createUser)
    {
        $this->createUser = $createUser;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface
    {
        return $this->updateDate;
    }

    public function setUpdateDate(\DateTimeInterface $updateDate): self
    {
        $this->updateDate = $updateDate;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUpdateUser()
    {
        return $this->updateUser;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return KioskCollec
     */
    public function setUpdateUser( $updateUser)
    {
        $this->updateUser = $updateUser;

        return $this;
    }
}
