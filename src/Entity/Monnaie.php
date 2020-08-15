<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Monnaie
 *
 * @ORM\Table(name="monnaie")
 * @ORM\Entity(repositoryClass="App\Repository\MonnaieRepository")
 */
class Monnaie
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
     * @ORM\Column(name="symbole", type="string", length=10)
     */
    private $symbole;

    /**
     * @var string
     *
     * @ORM\Column(name="libelle", type="string", length=255)
     */
    private $libelle;

    /**
     * @var string
     *
     * @ORM\Column(name="diminutif", type="string", length=5)
     */
    private $diminutif;

    /**
     * @var bool
     *
     * @ORM\Column(name="parDefault", type="boolean")
     */
    private $parDefault;

    /**
     * @var float
     *
     * @ORM\Column(name="valeur", type="float")
     */
    private $valeur;


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
     * Set symbole
     *
     * @param string $symbole
     *
     * @return Monnaie
     */
    public function setSymbole($symbole)
    {
        $this->symbole = $symbole;

        return $this;
    }

    /**
     * Get symbole
     *
     * @return string
     */
    public function getSymbole()
    {
        return $this->symbole;
    }

    /**
     * Set libelle
     *
     * @param string $libelle
     *
     * @return Monnaie
     */
    public function setLibelle($libelle)
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * Get libelle
     *
     * @return string
     */
    public function getLibelle()
    {
        return $this->libelle;
    }

    /**
     * Set diminutif
     *
     * @param string $diminutif
     *
     * @return Monnaie
     */
    public function setDiminutif($diminutif)
    {
        $this->diminutif = $diminutif;

        return $this;
    }

    /**
     * Get diminutif
     *
     * @return string
     */
    public function getDiminutif()
    {
        return $this->diminutif;
    }

    /**
     * Set parDefault
     *
     * @param boolean $parDefault
     *
     * @return Monnaie
     */
    public function setParDefault($parDefault)
    {
        $this->parDefault = $parDefault;

        return $this;
    }

    /**
     * Get parDefault
     *
     * @return bool
     */
    public function getParDefault()
    {
        return $this->parDefault;
    }

    /**
     * Set valeur
     *
     * @param float $valeur
     *
     * @return Monnaie
     */
    public function setValeur($valeur)
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * Get valeur
     *
     * @return float
     */
    public function getValeur()
    {
        return $this->valeur;
    }
}

