<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LienUserGame - Lien entre utilisateur et jeu vidéo
 *
 * @ORM\Table(name="lien_user_game")
 * @ORM\Entity(repositoryClass="App\Repository\LienUserGameRepository")
 */
class LienUserGame
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Game", inversedBy="userLinks")
     * @ORM\JoinColumn(name="game_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $game;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GameConsole")
     * @ORM\JoinColumn(name="console_id", referencedColumnName="id", nullable=true)
     */
    private $consoleEntity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GameTypeEdition")
     * @ORM\JoinColumn(name="type_edition_id", referencedColumnName="id", nullable=true)
     */
    private $typeEditionEntity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GameStore")
     * @ORM\JoinColumn(name="store_id", referencedColumnName="id", nullable=true)
     */
    private $storeEntity;

    /**
     * @var string|null Nom de l'édition (Standard, Collector, GOTY, etc.)
     *
     * @ORM\Column(name="nom_edition", type="string", length=100, nullable=true)
     */
    private $nomEdition;

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
     * @var string|null URL ou nom de fichier de l'image personnalisée
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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;
        return $this;
    }

    public function getTypeEdition(): string
    {
        return $this->typeEditionEntity?->getCode() ?? 'physique';
    }

    public function isPhysique(): bool
    {
        return $this->getTypeEdition() === 'physique';
    }

    public function isNumerique(): bool
    {
        return $this->getTypeEdition() === 'numerique';
    }

    public function getNomEdition(): ?string
    {
        return $this->nomEdition;
    }

    public function setNomEdition(?string $nomEdition): self
    {
        $this->nomEdition = $nomEdition;
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

    public function getConsoleEntity(): ?GameConsole
    {
        return $this->consoleEntity;
    }

    public function setConsoleEntity(?GameConsole $consoleEntity): self
    {
        $this->consoleEntity = $consoleEntity;
        return $this;
    }

    public function getTypeEditionEntity(): ?GameTypeEdition
    {
        return $this->typeEditionEntity;
    }

    public function setTypeEditionEntity(?GameTypeEdition $typeEditionEntity): self
    {
        $this->typeEditionEntity = $typeEditionEntity;
        return $this;
    }

    public function getStoreEntity(): ?GameStore
    {
        return $this->storeEntity;
    }

    public function setStoreEntity(?GameStore $storeEntity): self
    {
        $this->storeEntity = $storeEntity;
        return $this;
    }

    /**
     * Retourne le nom de la console (depuis l'entité si dispo, sinon l'ancien champ string)
     */
    public function getConsoleDisplay(): ?string
    {
        return $this->consoleEntity?->getCode();
    }

    /**
     * Retourne l'icône de la console
     */
    public function getConsoleIcone(): ?string
    {
        return $this->consoleEntity ? $this->consoleEntity->getIcone() : null;
    }

    /**
     * Retourne la couleur de la console
     */
    public function getConsoleCouleur(): ?string
    {
        return $this->consoleEntity ? $this->consoleEntity->getCouleur() : null;
    }

    /**
     * Code console pour filtres / badges : FK si présente, sinon valeur brute du champ historique.
     */
    public function getResolvedConsoleCode(): ?string
    {
        return $this->consoleEntity?->getCode();
    }

    /**
     * Libellé pour affichage (nom canonique ou chaîne brute).
     */
    public function getConsoleLabelForDisplay(): ?string
    {
        return $this->consoleEntity?->getNom();
    }

    /**
     * Nom du store pour affichage (édition numérique).
     */
    public function getStoreDisplay(): ?string
    {
        if (!$this->isNumerique()) {
            return null;
        }
        return $this->storeEntity?->getNom();
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
        if ($this->imagePerso) {
            if (filter_var($this->imagePerso, FILTER_VALIDATE_URL)) {
                return $this->imagePerso;
            }
            return '/uploads/game/user/' . $this->imagePerso;
        }
        return null;
    }
}
