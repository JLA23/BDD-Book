<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Livre
 *
 * @ORM\Table(name="livre")
 * @ORM\Entity(repositoryClass="App\Repository\LivreRepository")
 */
class Livre
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
     * @ORM\Column(name="titre", type="string", length=255)
     */
    private $titre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Category")
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=true)
     */
    private $category;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Collection")
     * @ORM\JoinColumn(name="collection_id", referencedColumnName="id", nullable=true)
     */
    private $collection;

    /**
     * @var string
     *
     * @ORM\Column(name="isbn", type="string", length=255, nullable=true)
     */
    private $isbn;

    /**
     * @var int
     *
     * @ORM\Column(name="numero", type="integer", nullable=true)
     */
    private $numero;

    /**
     * @var string
     *
     * @ORM\Column(name="annee", type="integer", nullable=true)
     */
    private $annee;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Edition")
     * @ORM\JoinColumn(name="edition_id", referencedColumnName="id", nullable=true)
     */
    private $edition;

    /**
     * @var string
     *
     * @ORM\Column(name="cycle", type="string", length=255, nullable=true)
     */
    private $cycle;

    /**
     * @var int
     *
     * @ORM\Column(name="tome", type="integer", nullable=true)
     */
    private $tome;

    /**
     * @var int
     *
     * @ORM\Column(name="pages", type="integer", nullable=true)
     */
    private $pages;

    /**
     * @var float
     *
     * @ORM\Column(name="prixBase", type="float", nullable=true)
     */
    private $prixBase;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Monnaie")
     * @ORM\JoinColumn(name="monnaie_id", referencedColumnName="id", nullable=true)
     */
    private $monnaie;

    /**
     * @var int
     *
     * @ORM\Column(name="cote", type="integer", nullable=true)
     */
    private $cote;

    /**
     * @var string
     *
     * @ORM\Column(name="amazon", type="text", nullable=true)
     */
    private $amazon;

    /**
     * @var float
     *
     * @ORM\Column(name="poids", type="float", nullable=true)
     */
    private $poids;

    /**
     * @var string
     *
     * @ORM\Column(name="resume", type="text", nullable=true)
     */
    private $resume;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LienAuteurLivre", mappedBy="livre", cascade={"persist", "remove"})
     */
    protected $listeAuteur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LienUserLivre", mappedBy="livre", cascade={"persist", "remove"})
     */
    protected $listeUser;


    /**
     * @var string
     *
     * @ORM\Column(name="image", type="blob", nullable=true)
     */
    private $image;



    /**
     * Constructor
     */
    public function __construct()
    {
        $this->listeAuteur = new ArrayCollection();
        $this->listeUser = new ArrayCollection();

    }


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
     * Set titre
     *
     * @param string $titre
     *
     * @return Livre
     */
    public function setTitre($titre)
    {
        $this->titre = $titre;

        return $this;
    }

    /**
     * Get titre
     *
     * @return string
     */
    public function getTitre()
    {
        return $this->titre;
    }

    /**
     * Set category
     *
     * @param Category $category
     *
     * @return Livre
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set collection
     *
     * @param Collection $collection
     *
     * @return Livre
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * Get collection
     *
     * @return Collection
     */
    public function getCollection()
    {
        return $this->collection;
    }


    /**
     * Set isbn
     *
     * @param string $isbn
     *
     * @return Livre
     */
    public function setIsbn($isbn)
    {
        $this->isbn= $isbn;

        return $this;
    }

    /**
     * Get isbn
     *
     * @return string
     */
    public function getIsbn()
    {
        return $this->isbn;
    }

    /**
     * Set numero
     *
     * @param integer $numero
     *
     * @return Livre
     */
    public function setNumero($numero)
    {
        $this->numero = $numero;

        return $this;
    }

    /**
     * Get numero
     *
     * @return int
     */
    public function getNumero()
    {
        return $this->numero;
    }

    /**
     * Set annee
     *
     * @param string $annee
     *
     * @return Livre
     */
    public function setAnnee($annee)
    {
        $this->annee = $annee;

        return $this;
    }

    /**
     * Get annee
     *
     * @return string
     */
    public function getAnnee()
    {
        return $this->annee;
    }

    /**
     * Set edition
     *
     * @param Edition $edition
     *
     * @return Livre
     */
    public function setEdition($edition)
    {
        $this->edition = $edition;

        return $this;
    }

    /**
     * Get edition
     *
     * @return Edition
     */
    public function getEdition()
    {
        return $this->edition;
    }

    /**
     * Set cycle
     *
     * @param string $cycle
     *
     * @return Livre
     */
    public function setCycle($cycle)
    {
        $this->cycle = $cycle;

        return $this;
    }

    /**
     * Get cycle
     *
     * @return string
     */
    public function getCycle()
    {
        return $this->cycle;
    }

    /**
     * Set tome
     *
     * @param integer $tome
     *
     * @return Livre
     */
    public function setTome($tome)
    {
        $this->tome = $tome;

        return $this;
    }

    /**
     * Get tome
     *
     * @return int
     */
    public function getTome()
    {
        return $this->tome;
    }

    /**
     * Set pages
     *
     * @param integer $pages
     *
     * @return Livre
     */
    public function setPages($pages)
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * Get pages
     *
     * @return int
     */
    public function getPages()
    {
        return $this->pages;
    }

    /**
     * Set prixBase
     *
     * @param float $prixBase
     *
     * @return Livre
     */
    public function setPrixBase($prixBase)
    {
        $this->prixBase = $prixBase;

        return $this;
    }

    /**
     * Get prixBase
     *
     * @return float
     */
    public function getPrixBase()
    {
        return $this->prixBase;
    }

    /**
     * Set monnaie
     *
     * @param Monnaie $monnaie
     *
     * @return Livre
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
     * Set cote
     *
     * @param integer $cote
     *
     * @return Livre
     */
    public function setCote($cote)
    {
        $this->cote = $cote;

        return $this;
    }

    /**
     * Get cote
     *
     * @return int
     */
    public function getCote()
    {
        return $this->cote;
    }

    /**
     * Set amazon
     *
     * @param string $amazon
     *
     * @return Livre
     */
    public function setAmazon($amazon)
    {
        $this->amazon = $amazon;

        return $this;
    }

    /**
     * Get amazon
     *
     * @return string
     */
    public function getAmazon()
    {
        return $this->amazon;
    }

    /**
     * Set poids
     *
     * @param float $poids
     *
     * @return Livre
     */
    public function setPoids($poids)
    {
        $this->poids = $poids;

        return $this;
    }

    /**
     * Get poids
     *
     * @return float
     */
    public function getPoids()
    {
        return $this->poids;
    }

    /**
     * Set resume
     *
     * @param string $resume
     *
     * @return Livre
     */
    public function setResume($resume)
    {
        $this->resume = $resume;

        return $this;
    }

    /**
     * Get resume
     *
     * @return string
     */
    public function getResume()
    {
        return $this->resume;
    }

    /**
     * Set image
     *
     * @param string $image
     *
     * @return Livre
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get image
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    public function getImage64(){
        return base64_encode(stream_get_contents($this->image));
    }

    /**
     * Add Auteur
     *
     * @param \App\Entity\LienAuteurLivre $auteur
     *
     * @return Livre
     */
    public function addAuteur(LienAuteurLivre $auteur)
    {
        $this->listeAuteur[] = $auteur;

        return $this;
    }

    /**
     * Remove Auteur
     *
     * @param \App\Entity\LienAuteurLivre $auteur
     */
    public function removeAuteur(LienAuteurLivre $auteur)
    {
        $this->listeAuteur->removeElement($auteur);
    }

    /**
     * Get listeAuteur
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getListeAuteur()
    {
        return $this->listeAuteur;
    }

    /**
     * Set listeAuteur
     *
     * @param \Doctrine\Common\Collections\Collection $listeAuteur
     *
     * @return Livre
     */
    public function setListeAuteur($listeAuteur)
    {
        foreach ($this->getListeAuteur() as $c)
            $this->removeAuteur($c);


        foreach ($listeAuteur as $c)
            $this->addAuteur($c);

        return $this;
    }

    /**
     * Add User
     *
     * @param \App\Entity\LienUserLivre $user
     *
     * @return Livre
     */
    public function addUser(LienUserLivre $user)
    {
        $this->listeUser[] = $user;

        return $this;
    }

    /**
     * Remove User
     *
     * @param \App\Entity\LienUserLivre $user
     */
    public function removeUser(LienAuteurLivre $user)
    {
        $this->listeUser->removeElement($user);
    }

    /**
     * Get listeUser
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getListeUser()
    {
        return $this->listeUser;
    }

    /**
     * Set listeUser
     *
     * @param \Doctrine\Common\Collections\Collection $listeUser
     *
     * @return Livre
     */
    public function setListeUser($listeUser)
    {
        foreach ($this->getListeUser() as $c)
            $this->removeUser($c);


        foreach ($listeUser as $c)
            $this->addUser($c);

        return $this;
    }
}

