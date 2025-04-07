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

class RecoverBDDV2Command extends Command
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
            ->setName('RecoverBDD_V2')
            ->setDescription('...');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $servername = $_ENV['IP_BDD_TEMP'];
        $username = $_ENV['USER_BDD_TEMP'];
        $password = $_ENV['PWD_BDD_TEMP'];

        $em = $this->container->get('doctrine')->getManager();
        $pdo = new \PDO('mysql:host='.$servername.';dbname='.$_ENV['NAME_BDD_TEMP'], $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));

        $sql = "SELECT * FROM Traitement";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        $dateTraitement = $row['DateTrait'];

        $sql = "SELECT * FROM Matiere";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Matiere'])) {
                $categorie = $em->getRepository(\App\Entity\Category::class)->getCategoryByName($row['Matiere']);
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
            $cat = str_replace("\r",'', $row['Categorie'] );
            $cat = str_replace("\n", '', $row['Categorie'] );
            if (str_contains($cat, ';')){
                $catE = explode(';', $cat);
                $cat = $catE[0];
            }
            $cat = trim($cat);
            if(!empty($cat)) {
                $edition = $em->getRepository(\App\Entity\Edition::class)->getEditionByName($cat);
                if (!$edition) {
                    $edition = new Edition();
                    $edition->setNom($cat);
                    $em->persist($edition);
                    $em->flush();
                }
            }
        }

        $sql = "SELECT * FROM Etat";
        foreach  ($pdo->query($sql) as $row) {
            if(!empty($row['Etat'])) {
                $collection = $em->getRepository(\App\Entity\Collection::class)->getCollectionByName($row['Etat']);
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
                        $auteur = $em->getRepository(\App\Entity\Auteur::class)->getAuteurByName(trim($aut));
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
        $monnaie = $em->getRepository(\App\Entity\Monnaie::class)->findOneById(1);
        $users = $em->getRepository(\App\Entity\User::class)->findAll();

        foreach ($users as $user){
            $sql = 'SELECT * FROM Monnaie WHERE Traite = 0 AND COL_TYPE = "'.$user->getIdAccess().'"';
            $result = $pdo->query($sql);
            $compteur = 0;
            foreach  ($result as $row) {
                $compteur ++;
                if(!empty($row['Particularite'])) {
                    $edition = $em->getRepository(\App\Entity\Edition::class)->getEditionByName($row['Categorie']);
                    $lul = $em->getRepository(\App\Entity\LienUserLivre::class)->getLivreByUserAndSeq($user, $row['Seq']);
                    //$livre = $em->getRepository('App:Livre')->getLivreBySeq($row['Seq'], $user);
                    if ($lul) {
                        $livre = $lul->getLivre();
                        if (($livre->getTitre() != $row['Particularite']) and ($livre->getIsbn != $row['Classeur'])){
                            $livre = $em->getRepository(\App\Entity\Livre::class)->getLivreByInfos($row['Particularite'], $row['Classeur'], $edition);
                        }
                    }
                    else {
                        $livre = $em->getRepository(\App\Entity\Livre::class)->getLivreByInfos($row['Particularite'], $row['Classeur'], $edition);
                    }
                    if(!$livre){
                        $livre = new Livre();
                        $livre->setTitre($row['Particularite']);
                        $livre->setTome($row['Page']);
                        $livre->setAnnee($row['Année']);
                        $livre->setImage($row['Avers']);
                        $livre->setPrixBase(floatval($row['Valeur estimée']));
                        $livre->setPages($row['Diametre']);
                        $livre->setIsbn(trim($row['Classeur']));
                        $livre->setAmazon($row['Poid']);
                        $livre->setResume($row['Notes']);

                        if($monnaie){
                            $livre->setMonnaie($monnaie);
                        }
                        if($edition){
                            $livre->setEdition($edition);
                        }

                        $category = $em->getRepository(\App\Entity\Category::class)->getCategoryByName($row['Matière']);
                        if($category){
                            $livre->setCategory($category);
                        }

                        $collection = $em->getRepository(\App\Entity\Collection::class)->getCollectionByName($row['Etat']);
                        if($collection){
                            $livre->setCollection($collection);
                        }
                        $em->persist($livre);
                        $em->flush();
                    }
                    else{
                        $this->comparaisonLivre($row, $livre, $em);
                    }

                    $lienLivreUser = $em->getRepository(\App\Entity\LienUserLivre::class)->getLienByUserAndLivre($user, $livre, intval($row['Seq']));

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
                            $auteur = $em->getRepository(\App\Entity\Auteur::class)->getAuteurByName(trim($aut));
                            if ($auteur) {
                                $lienAuteurLivre = $em->getRepository(\App\Entity\LienAuteurLivre::class)->getLienByAuteurAndLivre($auteur, $livre);
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
                    $listeAuteursBDD = $em->getRepository(\App\Entity\LienAuteurLivre::class)->getListeAuteur($livre);
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
            $output->writeln('result : '.$compteur);
            $output->writeln('user : '.$user->getUsername());



            //Suppression des livres enlever de la liste
            /*$listeLivresBDD = $em->getRepository('App:LienUserLivre')->getLivreByUser($user);
            foreach ($listeLivresBDD as $ll){
                $sql = 'SELECT * FROM Monnaie WHERE COL_TYPE = "'.$user->getIdAccess().'" AND (Seq = '.$ll->getSeq().' OR Particularite = ? )';
                $stmt = $pdo->prepare($sql);
                $stmt->bindparam(1, $row['Particularite'],  \PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAllAssociative();
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
            }*/

            $sql = "SELECT * FROM Monnaie WHERE COL_TYPE = '".$user->getIdAccess()."' AND Traite = -1";
            $result = $pdo->query($sql);
            foreach  ($result as $row) {
                if (!empty($row['Particularite'])) {

                    $edition = $em->getRepository(\App\Entity\Edition::class)->getEditionByName($row['Categorie']);
                    /**$edition_id = null;
                    if ($edition) {
                    $edition_id = $edition->getId();
                    }*/
                    $livre = $em->getRepository(\App\Entity\Livre::class)->getLivreByInfos($row['Particularite'], $row['Classeur'], $edition);
                    if ($livre) {
                        $listeLienUserLivre = $em->getRepository(\App\Entity\LienUserLivre::class)->getListeUserByLivre($livre);
                        foreach ($listeLienUserLivre as $lul){
                            if($lul->getUser()->getId() == $user->getId()){
                                $em->remove($lul);
                                $em->flush();
                            }
                        }
                        $listeLienUserLivre = $em->getRepository(\App\Entity\LienUserLivre::class)->getListeUserByLivre($livre);
                        if (count($listeLienUserLivre) == 0){
                            $listeAuteursBDD = $em->getRepository(\App\Entity\LienAuteurLivre::class)->getListeAuteur($livre);
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

        $listeAuteurs = $em->getRepository(\App\Entity\Auteur::class)->findAll();
        foreach ($listeAuteurs as $a){
            if($em->getRepository(\App\Entity\LienAuteurLivre::class)->getCountLien($a) == 0){
                $em->remove($a);
                $em->flush();
            }
        }

        $pdo = null;

        return Command::SUCCESS;
    }

    public function comparaisonLivre($row, Livre $livre, $em){
        //if(strlen($row['Particularite']) > strlen($livre->getTitre())) {
            $livre->setTitre($row['Particularite']);
        //}
        $livre->setTome($row['Page']);
        $livre->setAnnee($row['Année']);
        $livre->setImage($row['Avers']);
        $livre->setPrixBase(floatval($row['Valeur estimée']));
        $livre->setPages($row['Diametre']);
        $livre->setIsbn(trim($row['Classeur']));
        $livre->setAmazon($row['Poid']);
        $livre->setResume($row['Notes']);

        $edition = $em->getRepository(\App\Entity\Edition::class)->getEditionByName($row['Categorie']);
        if($edition){
            $livre->setEdition($edition);
        }

        $category = $em->getRepository(\App\Entity\Category::class)->getCategoryByName($row['Matière']);
        if($category){
            $livre->setCategory($category);
        }

        $collection = $em->getRepository(\App\Entity\Collection::class)->getCollectionByName($row['Etat']);
        if($collection){
            $livre->setCollection($collection);
        }
        $em->persist($livre);
        $em->flush();
    }
}
