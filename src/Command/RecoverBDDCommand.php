<?php
/**
 * Created by PhpStorm.
 * User: Eric LEFEBVRE
 * Date: 02/08/20
 * Time: 15:45
 */

namespace App\Command;

use App\Entity\Auteur;
use App\Entity\Category;
use App\Entity\Edition;
use App\Entity\Format;
use App\Entity\LienAuteurLivre;
use App\Entity\LienUserLivre;
use App\Entity\Livre;
use App\Entity\Monnaie;
use App\Entity\Collection;
use App\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RecoverBDDCommand extends Command
{
    public $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
            ->setName('RecoverBDD')
            ->setDescription('...');
            //->addArgument('user', InputArgument::REQUIRED, 'Argument user id')
            //->addArgument('file', InputArgument::REQUIRED, 'Argument fichier CSV')
            //->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $servername = "localhost";
        $username = "eric";
        $password = "root";

        $em = $this->container->get('doctrine')->getManager();
        $pdo = new \PDO('mysql:host='.$servername.';dbname=DatabaseBook', $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));

        $sql = "SELECT * FROM Traitement";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        $dateTraitement = $row['DateTrait'];

        $sql = "SELECT * FROM Matiere";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Matiere'])) {
                $categorie = $em->getRepository('App:Category')->getCategoryByName($row['Matiere']);
                if (!$categorie) {
                    $categorie = new Category();
                    $categorie->setNom($row['Matiere']);
                    $em->persist($categorie);
                    $em->flush();
                }
            }
        }

        $sql = "SELECT * FROM Categorie";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Categorie'])) {
                $edition = $em->getRepository('App:Edition')->getEditionByName($row['Categorie']);
                if (!$edition) {
                    $edition = new Edition();
                    $edition->setNom($row['Categorie']);
                    $em->persist($edition);
                    $em->flush();
                }
            }
        }

        $sql = "SELECT * FROM Etat";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Etat'])) {
                $collection = $em->getRepository('App:Collection')->getCollectionByName($row['Etat']);
                if (!$collection) {
                    $collection = new Collection();
                    $collection->setNom($row['Etat']);
                    $em->persist($collection);
                    $em->flush();
                }
            }
        }

        $sql = "SELECT * FROM Pays";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Pays'])) {
                $auteurs = explode(',', $row['Pays']);
                foreach ($auteurs as $aut){
                    if($aut && trim($aut) <> "") {
                        $auteur = $em->getRepository('App:Auteur')->getAuteurByName(trim($aut));
                        if (!$auteur) {
                            $auteur = new Auteur();
                            $auteur->setNom($aut);
                            $em->persist($auteur);
                            $em->flush();
                        }
                    }
                }
            }
        }
        $monnaie = $em->getRepository('App:Monnaie')->findOneById(1);
        $users = $em->getRepository('App:User')->findAll();

        foreach ($users as $user){
            $sql = 'SELECT * FROM Monnaie WHERE Traite = 0 AND COL_TYPE = "'.$user->getIdAccess().'"';
            $result = $pdo->query($sql);
            foreach  ($result as $row) {
                if(!empty($row['Particularite'])) {
                    $edition = $em->getRepository('App:Edition')->getEditionByName($row['Categorie']);
                    $livre = $em->getRepository('App:Livre')->getLivreBySeq($row['Seq'], $user);
                    if(!$livre){
                        $livre = $em->getRepository('App:Livre')->getLivreByInfos($row['Particularite'], $row['Classeur'], $edition);
                    }
                    if(!$livre){
                        $livre = new Livre();
                        $livre->setTitre($row['Particularite']);
                        $livre->setTome($row['Page']);
                        $livre->setAnnee($row['Année']);
                        $livre->setImage($row['Avers']);
                        $livre->setPrixBase(floatval($row['Valeur estimée']));
                        $livre->setPages($row['Diametre']);
                        $livre->setIsbn($row['Classeur']);
                        $livre->setAmazon($row['Poid']);
                        $livre->setResume($row['Notes']);

                        if($monnaie){
                            $livre->setMonnaie($monnaie);
                        }
                        if($edition){
                            $livre->setEdition($edition);
                        }

                        $category = $em->getRepository('App:Category')->getCategoryByName($row['Matière']);
                        if($category){
                            $livre->setCategory($category);
                        }

                        $collection = $em->getRepository('App:Collection')->getCollectionByName($row['Etat']);
                        if($collection){
                            $livre->setCollection($collection);
                        }
                        $em->persist($livre);
                        $em->flush();
                    }
                    else{
                        $this->comparaisonLivre($row, $livre, $em);
                    }

                    $lienLivreUser = $em->getRepository('App:LienUserLivre')->getLienByUserAndLivre($user, $livre, intval($row['Seq']));

                    if (!$lienLivreUser){
                        $lienLivreUser = new LienUserLivre();
                        $lienLivreUser->setMonnaie($monnaie);
                        $lienLivreUser->setPrixAchat(floatval($row['Valeur estimée']));
                        $lienLivreUser->setLivre($livre);
                        $lienLivreUser->setUser($user);
                        $lienLivreUser->setSeq(intval($row['Seq']));
                        $lienLivreUser->setCommentaire($row['CommPerso']);
                        $lienLivreUser->setParticularite($row['Graveur']);

                    }
                    else{
                        $lienLivreUser->setPrixAchat(floatval($row['Valeur estimée']));
                        $lienLivreUser->setLivre($livre);
                        $lienLivreUser->setUser($user);
                        $lienLivreUser->setCommentaire($row['CommPerso']);
                        $lienLivreUser->setParticularite($row['Graveur']);
                    }
                    $em->persist($lienLivreUser);
                    $em->flush();

                    $auteurs = explode(',', $row['Pays']);
                    /**if($row['Seq'] == '820'){
                        echo $row['Particularite'] . " - " .$row['Pays'] . "\n";
                    }*/
                    foreach ($auteurs as $aut){
                        if($aut && trim($aut) <> "") {
                            $auteur = $em->getRepository('App:Auteur')->getAuteurByName(trim($aut));
                            if ($auteur) {
                                $lienAuteurLivre = $em->getRepository('App:LienAuteurLivre')->getLienByAuteurAndLivre($auteur, $livre);
                                if (!$lienAuteurLivre) {
                                    $lienAuteurLivre = new LienAuteurLivre();
                                    $lienAuteurLivre->setLivre($livre);
                                    $lienAuteurLivre->setAuteur($auteur);

                                    $em->persist($lienAuteurLivre);
                                    $em->flush();
                                }
                            } else {
                                $auteur = new Auteur();
                                $auteur->setNom($aut);
                                $em->persist($auteur);
                                $em->flush();
                                $lienAuteurLivre = new LienAuteurLivre();
                                $lienAuteurLivre->setLivre($livre);
                                $lienAuteurLivre->setAuteur($auteur);

                                $em->persist($lienAuteurLivre);
                                $em->flush();
                            }
                        }
                    }
                    $listeAuteursBDD = $em->getRepository('App:LienAuteurLivre')->getListeAuteur($livre);
                    foreach ($listeAuteursBDD as $la){
                        $trouve = false;
                        foreach ($auteurs as $a) {
                            if (strtoupper($la->getAuteur()->getNom()) == strtoupper($a)) {
                               $trouve = true;
                               break;
                            }
                        }
                        if(!$trouve){
                            $em->remove($la);
                            $em->flush();
                        }
                    }
                }

                $sql = 'UPDATE Monnaie SET Traite = 1 WHERE Seq = '.$row['Seq'].' AND COL_TYPE = "'.$row['COL_TYPE'].'" AND (Classeur = "'.$row['Classeur'].'" OR Particularite = ?)';
                $stmt = $pdo->prepare($sql);
                $stmt->bindparam(1, $row['Particularite'],  \PDO::PARAM_STR);
                $stmt->execute();
            }

            //Suppression des livres enlever de la liste
            $listeLivresBDD = $em->getRepository('App:LienUserLivre')->getLivreByUser($user);
            foreach ($listeLivresBDD as $ll){
                $sql = 'SELECT * FROM Monnaie WHERE COL_TYPE = "'.$user->getIdAccess().'" AND (Seq = '.$ll->getSeq().' OR Particularite = ? )';
                $stmt = $pdo->prepare($sql);
                $stmt->bindparam(1, $row['Particularite'],  \PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAll();
                if(empty($result)){
                    $l = $ll->getLivre();
                    $em->remove($ll);
                    $em->flush();
                    $em->refresh($l);
                    $listeLienUserLivre = $em->getRepository('App:LienUserLivre')->getListeUserByLivre($l);
                    if(count($listeLienUserLivre) == 0){
                        $listeAuteursBDD = $em->getRepository('App:LienAuteurLivre')->getListeAuteur($l);
                        foreach ($listeAuteursBDD as $la){
                            $em->remove($la);
                        }
                        $em->remove($l);
                        $em->flush();
                    }
                }
            }

            $sql = "SELECT * FROM Monnaie WHERE COL_TYPE = '".$user->getIdAccess()."' AND DateLastTraite <> '".$dateTraitement."'";
            $result = $pdo->query($sql);
            foreach  ($result as $row) {
                if (!empty($row['Particularite'])) {

                    $edition = $em->getRepository('App:Edition')->getEditionByName($row['Categorie']);
                    /**$edition_id = null;
                    if ($edition) {
                        $edition_id = $edition->getId();
                    }*/
                    $livre = $em->getRepository('App:Livre')->getLivreByInfos($row['Particularite'], $row['Classeur'], $edition);
                    if ($livre) {
                        $listeLienUserLivre = $em->getRepository('App:LienUserLivre')->getListeUserByLivre($livre);
                        foreach ($listeLienUserLivre as $lul){
                            if($lul->getUser()->getId() == $user->getId()){
                                $em->remove($lul);
                                $em->flush();
                            }
                        }
                        $listeLienUserLivre = $em->getRepository('App:LienUserLivre')->getListeUserByLivre($livre);
                        if (count($listeLienUserLivre) == 0){
                            $listeAuteursBDD = $em->getRepository('App:LienAuteurLivre')->getListeAuteur($livre);
                            foreach ($listeAuteursBDD as $la){
                                $em->remove($la);
                                $em->flush();
                            }
                            $em->remove($livre);
                            $em->flush();
                        }
                    }
                    $sql = 'DELETE FROM Monnaie WHERE Seq = '.$row['Seq'].' AND COL_TYPE = "'.$row['COL_TYPE'].'" AND (Classeur = "'.$row['Classeur'].'" OR Particularite = ? )';
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindparam(1, $row['Particularite'],  \PDO::PARAM_STR);
                    $stmt->execute();
                }
            }
        }

        $listeAuteurs = $em->getRepository('App:Auteur')->findAll();
        foreach ($listeAuteurs as $a){
            if($em->getRepository('App:LienAuteurLivre')->getCountLien($a) == 0){
                $em->remove($a);
                $em->flush();
            }
        }

        $pdo = null;

        return Command::SUCCESS;
    }

    public function comparaisonLivre($row, Livre $livre, $em){
        if(strlen($row['Particularite']) > strlen($livre->getTitre())) {
            $livre->setTitre($row['Particularite']);
        }
        $livre->setTome($row['Page']);
        $livre->setAnnee($row['Année']);
        $livre->setImage($row['Avers']);
        $livre->setPrixBase(floatval($row['Valeur estimée']));
        $livre->setPages($row['Diametre']);
        $livre->setIsbn($row['Classeur']);
        $livre->setAmazon($row['Poid']);
        $livre->setResume($row['Notes']);

        $edition = $em->getRepository('App:Edition')->getEditionByName($row['Categorie']);
        if($edition){
            $livre->setEdition($edition);
        }

        $category = $em->getRepository('App:Category')->getCategoryByName($row['Matière']);
        if($category){
            $livre->setCategory($category);
        }

        $collection = $em->getRepository('App:Collection')->getCollectionByName($row['Etat']);
        if($collection){
            $livre->setCollection($collection);
        }
        $em->persist($livre);
        $em->flush();
    }
}
