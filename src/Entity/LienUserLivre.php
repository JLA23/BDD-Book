<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LienUserLivre
 *
 * @ORM\Table(name="lien_user_livre")
 * @ORM\Entity(repositoryClass="App\Repository\LienUserLivreRepository")
 */
class LienUserLivre
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Livre")
     * @ORM\JoinColumn(name="livre_id", referencedColumnName="id", nullable=false)
     */
    private $livre;

    /**
     * @var string
     *
     * @ORM\Column(name="note", type="integer", nullable=true)
     */
    private $note;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dateAchat", type="datetime", nullable=true)
     */
    private $dateAchat;

    /**
     * @var float
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
     * @var string
     *
     * @ORM\Column(name="commentaire", type="text", nullable=true)
     */
    private $commentaire;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set user
     *
     * @param User $user
     *
     * @return LienUserLivre
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
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
     * Set livre
     *
     * @param Livre $livre
     *
     * @return LienUserLivre
     */
    public function setLivre($livre)
    {
        $this->livre = $livre;

        return $this;
    }

    /**
     * Get livre
     *
     * @return Livre
     */
    public function getLivre()
    {
        return $this->livre;
    }

    /**
     * Set note
     *
     * @param string $note
     *
     * @return LienUserLivre
     */
    public function setNote($note)
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Get note
     *
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Set dateAchat
     *
     * @param \DateTime $dateAchat
     *
     * @return LienUserLivre
     */
    public function setDateAchat($dateAchat)
    {
        $this->dateAchat = $dateAchat;

        return $this;
    }

    /**
     * Get dateAchat
     *
     * @return \DateTime
     */
    public function getDateAchat()
    {
        return $this->dateAchat;
    }

    /**
     * Set prixAchat
     *
     * @param float $prixAchat
     *
     * @return LienUserLivre
     */
    public function setPrixAchat($prixAchat)
    {
        $this->prixAchat = $prixAchat;

        return $this;
    }

    /**
     * Get prixAchat
     *
     * @return float
     */
    public function getPrixAchat()
    {
        return $this->prixAchat;
    }

    /**
     * Set monnaie
     *
     * @param Monnaie $monnaie
     *
     * @return LienUserLivre
     */
    public function setMonnaie($monnaie)
    {
        $this->monnaie = $monnaie;

        return $this;
    }

    /**
     * Get monnaie
     *
     * @return Monnaie
     */
    public function getMonnaie()
    {
        return $this->monnaie;
    }

    /**
     * Set commentaire
     *
     * @param string $commentaire
     *
     * @return LienUserLivre
     */
    public function setCommentaire($commentaire)
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * Get commentaire
     *
     * @return string
     */
    public function getCommentaire()
    {
        return $this->commentaire;
    }
}

