<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LienUserBrickSet - Lien entre un utilisateur et un set
 *
 * @ORM\Table(name="lien_user_brick_set")
 * @ORM\Entity(repositoryClass="App\Repository\LienUserBrickSetRepository")
 */
class LienUserBrickSet
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
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BrickSet", inversedBy="listeUser")
     * @ORM\JoinColumn(name="brick_set_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $brickSet;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_achat", type="datetime", nullable=true)
     */
    private $dateAchat;

    /**
     * @var float|null
     *
     * @ORM\Column(name="prix_achat", type="float", nullable=true)
     */
    private $prixAchat;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Monnaie")
     * @ORM\JoinColumn(name="monnaie_id", referencedColumnName="id", nullable=true)
     */
    private $monnaie;

    /**
     * @var string|null
     *
     * @ORM\Column(name="commentaire", type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @var bool
     *
     * @ORM\Column(name="est_monte", type="boolean")
     */
    private $estMonte = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="est_complet", type="boolean")
     */
    private $estComplet = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getBrickSet(): ?BrickSet
    {
        return $this->brickSet;
    }

    public function setBrickSet(BrickSet $brickSet): self
    {
        $this->brickSet = $brickSet;
        return $this;
    }

    public function getDateAchat(): ?\DateTime
    {
        return $this->dateAchat;
    }

    public function setDateAchat(?\DateTime $dateAchat): self
    {
        $this->dateAchat = $dateAchat;
        return $this;
    }

    public function getPrixAchat(): ?float
    {
        return $this->prixAchat;
    }

    public function setPrixAchat(?float $prixAchat): self
    {
        $this->prixAchat = $prixAchat;
        return $this;
    }

    public function getMonnaie(): ?Monnaie
    {
        return $this->monnaie;
    }

    public function setMonnaie(?Monnaie $monnaie): self
    {
        $this->monnaie = $monnaie;
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

    public function getEstMonte(): bool
    {
        return $this->estMonte;
    }

    public function setEstMonte(bool $estMonte): self
    {
        $this->estMonte = $estMonte;
        return $this;
    }

    public function getEstComplet(): bool
    {
        return $this->estComplet;
    }

    public function setEstComplet(bool $estComplet): self
    {
        $this->estComplet = $estComplet;
        return $this;
    }
}
