<?php

namespace App\Entity;

use App\Repository\LienKioskNumUserRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LienKioskNumUserRepository::class)
 */
class LienKioskNumUser
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="idUser", referencedColumnName="id", nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\KioskNum")
     * @ORM\JoinColumn(name="idKioskNum", referencedColumnName="id", nullable=false)
     */
    private $kioskNum;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return LienKioskNumUser
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }
    /**
     * Get KioskNum
     *
     * @return KioskNum
     */
    public function getKioskNum()
    {
        return $this->kioskNum;
    }

    /**
     * Set kioskNum
     *
     * @param kioskNum $kioskNum
     *
     * @return LienKioskNumUser
     */
    public function setKioskNum($kioskNum)
    {
        $this->kioskNum = $kioskNum;

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
}
