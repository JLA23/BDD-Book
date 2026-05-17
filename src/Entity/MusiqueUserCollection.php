<?php

namespace App\Entity;

use App\Entity\Trait\StoredImagePathTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * MusiqueUserCollection - Lien entre utilisateur et Musique
 *
 * @ORM\Table(name="musique_user_collection")
 * @ORM\Entity(repositoryClass="App\Repository\MusiqueUserCollectionRepository")
 */
class MusiqueUserCollection
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
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Musique", inversedBy="userLinks")
     * @ORM\JoinColumn(name="musique_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $musique;

    /**
     * @var float|null Prix d'achat
     *
     * @ORM\Column(name="prix_achat", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $prixAchat;

    /**
     * @var \DateTime|null Date d'achat
     *
     * @ORM\Column(name="date_achat", type="date", nullable=true)
     */
    private $dateAchat;

    /**
     * @var string|null URL ou nom de fichier de l'image personnalisee
     *
     * @ORM\Column(name="image_perso", type="string", length=500, nullable=true)
     */
    private $imagePerso;

    /**
     * @var string|null Commentaire
     *
     * @ORM\Column(name="commentaire", type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getMusique(): ?Musique
    {
        return $this->musique;
    }

    public function setMusique(?Musique $musique): self
    {
        $this->musique = $musique;
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

    public function getDateAchat(): ?\DateTime
    {
        return $this->dateAchat;
    }

    public function setDateAchat(?\DateTime $dateAchat): self
    {
        $this->dateAchat = $dateAchat;
        return $this;
    }

    public function getImagePerso(): ?string
    {
        return $this->imagePerso;
    }

    public function setImagePerso(?string $imagePerso): self
    {
        $this->imagePerso = $imagePerso;
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getDisplayImage(): ?string
    {
        if (!empty($this->storedPath)) {
            return $this->storedPath;
        }

        if ($this->imagePerso) {
            if (filter_var($this->imagePerso, FILTER_VALIDATE_URL)) {
                return $this->imagePerso;
            }

            return '/uploads/musique/user/' . $this->imagePerso;
        }

        return null;
    }
}
