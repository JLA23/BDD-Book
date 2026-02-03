<?php
// src/Command/CreateUserCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use App\Entity\User;

class CreateUserCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-user';
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }


    protected function configure()
    {
        $this
            ->setName('app:create-user')
            ->setDescription('Création d\'un utilisateur')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $error = false;

        do{
            $question = new Question('Veuillez entrer un nom d\'utilisateur : ');
            $username = $helper->ask($input, $output, $question);
            if(empty(trim($username))){
                $error = true;
                $output->writeln('Nom d\'utilisateur invalide !');
            }
            else{
                $error = false;
            }
        }while($error);

        do{
            $question = new Question('Veuillez entrer une adresse e-mail : ');
            $email = $helper->ask($input, $output, $question);
            if(empty(trim($email)) || !$this->verifMail($email)){
                $error = true;
                $output->writeln('E-mail invalide !');
            }
            else{
                $error = false;
            }
        }while($error);

        do{
            $question = new Question('Veuillez entrer un mot de passe : ');
            $password = $helper->ask($input, $output, $question);
            if(empty(trim($password))){
                $error = true;
                $output->writeln('Le mot de passe ne peut pas etre vide !');
            }
            else{
                $error = false;
            }
        }while($error);

        do{
            $question = new Question('Veuillez entrer un nom : ');
            $nom = $helper->ask($input, $output, $question);
            if(empty(trim($nom))){
                $error = true;
                $output->writeln('Le nom ne peut pas etre vide !');
            }
            else{
                $error = false;
            }
        }while($error);

        do{
            $question = new Question('Veuillez entrer un prenom : ');
            $prenom = $helper->ask($input, $output, $question);
            if(empty(trim($prenom))){
                $error = true;
                $output->writeln('Le prenom ne peut pas etre vide !');
            }
            else{
                $error = false;
            }
        }while($error);

        do{
            $question = new Question('Veuillez entrer le nom de la bibliotheque dans MesLivres Pro : ');
            $idAccess = $helper->ask($input, $output, $question);
            if(empty(trim($idAccess))){
                $error = true;
                $output->writeln('Le nom de la bibliotheque dans MesLivres Pro ne peut pas etre vide !');
            }
            else{
                $error = false;
            }
        }while($error);


        $this->create($username, $password, $email, $prenom, $nom, $idAccess);

    return Command::SUCCESS;


    }

    public function create($username, $password, $email, $prenom, $nom, $idAccess)
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setLastname($nom);
        $user->setName($prenom);
        $user->setIdAccess($idAccess);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

    }

    public function verifMail(string $mail)
    {
        // L'adresse doit contenir un @, un point et 2 caractères de chaque coté
        return (preg_match('#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#', $mail)) ? true : false;
    }



}