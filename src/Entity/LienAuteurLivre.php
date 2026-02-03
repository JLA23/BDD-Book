<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LienAuteurLivre
 *
 * @ORM\Table(name="lien_auteur_livre")
 * @ORM\Entity(repositoryClass="App\Repository\LienAuteurLivreRepository")
 */
class LienAuteurLivre
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Livre", inversedBy="listeAuteur")
     * @ORM\JoinColumn(name="livre_id", referencedColumnName="id", nullable=false)
     */
    private $livre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Auteur")
     * @ORM\JoinColumn(name="auteur_id", referencedColumnName="id", nullable=false)
     */
    private $auteur;


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
     * Set livre
     *
     * @param Livre $livre
     *
     * @return LienAuteurLivre
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
     * Set auteur
     *
     * @param Auteur $auteur
     *
     * @return LienAuteurLivre
     */
    public function setAuteur($auteur)
    {
        $this->auteur = $auteur;

        return $this;
    }

    /**
     * Get auteur
     *
     * @return Auteur
     */
    public function getAuteur()
    {
        return $this->auteur;
    }

}

