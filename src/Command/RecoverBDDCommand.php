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
                    $auteur = $em->getRepository('App:Auteur')->getAuteurByName($aut);
                    if (!$auteur) {
                        $auteur = new Auteur();
                        $auteur->setNom($aut);
                        $em->persist($auteur);
                        $em->flush();
                    }
                }
            }
        }

        $users = $em->getRepository('App:User')->findAll();
        foreach ($users as $user){
            $sql = 'SELECT * FROM Monnaie WHERE Traite = 0 AND COL_TYPE = "'.$user->getIdAccess().'"';
            $result = $pdo->query($sql);
            foreach  ($result as $row) {
                if(!empty($row['Particularite'])) {
                    $livre = $em->getRepository('App:Livre')->getLivreByInfos($row['Particularite'], $row['Classeur']);
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

                        $monnaie = $em->getRepository('App:Monnaie')->findOneById(1);
                        if($monnaie){
                            $livre->setMonnaie($monnaie);
                        }

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
            }
        }

        $pdo = null;

        return Command::SUCCESS;
    }
}
