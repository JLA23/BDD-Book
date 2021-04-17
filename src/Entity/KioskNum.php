<?php

namespace App\Entity;

use App\Repository\KioskNumRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=KioskNumRepository::class)
 */
class KioskNum
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $num;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\KioskCollec")
     * @ORM\JoinColumn(name="idKioskCollec", referencedColumnName="id", nullable=false)
     */
    private $kioskCollec;

    /**
     * @ORM\Column(type="blob", nullable=true)
     */
    private $couverture;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $EAN;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $prix;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="date")
     */
    private $createDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="createUser", referencedColumnName="id", nullable=false)
     */
    private $createUser;

    /**
     * @ORM\Column(type="date")
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

    public function getNum(): ?int
    {
        return $this->num;
    }

    public function setNum(int $num): self
    {
        $this->num = $num;

        return $this;
    }

    /**
     * Get KioskCollec
     *
     * @return KioskCollec
     */
    public function getKioskCollec()
    {
        return $this->kioskCollec;
    }

    /**
     * Set kioskCollec
     *
     * @param KioskCollec $kioskCollec
     *
     * @return KioskNum
     */
    public function setKioskCollec($kioskCollec)
    {
        $this->kioskCollec = $kioskCollec;

        return $this;
    }

    public function getCouverture()
    {
        return $this->couverture;
    }

    public function setCouverture($couverture): self
    {
        $this->couverture = $couverture;

        return $this;
    }

    public function getEAN(): ?string
    {
        return $this->EAN;
    }

    public function setEAN(?string $EAN): self
    {
        $this->EAN = $EAN;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

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
     * @return KioskNum
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
     * @return KioskNum
     */
    public function setUpdateUser( $updateUser)
    {
        $this->updateUser = $updateUser;

        return $this;
    }
}
