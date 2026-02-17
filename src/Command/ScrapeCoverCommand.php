<?php

namespace App\Command;

use App\Entity\Livre;
use App\Entity\User;
use App\Service\BookCoverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScrapeCoverCommand extends Command
{
    protected static $defaultName = 'app:scrape-covers';
    protected static $defaultDescription = 'Scrape et télécharge les images de couverture pour tous les livres d\'un utilisateur';

    private $em;
    private $bookCoverService;
    private $projectDir;

    public function __construct(EntityManagerInterface $em, BookCoverService $bookCoverService, string $projectDir)
    {
        parent::__construct();
        $this->em = $em;
        $this->bookCoverService = $bookCoverService;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'ID de l\'utilisateur')
            ->addOption('delay', 'd', InputOption::VALUE_OPTIONAL, 'Délai en secondes entre chaque scraping (défaut: 3)', 3)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer le scraping même si une image existe déjà')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Nombre maximum de livres à traiter', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $userId = $input->getArgument('user-id');
        $delay = (int) $input->getOption('delay');
        $force = $input->getOption('force');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        // Vérifier que l'utilisateur existe
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            $io->error("Utilisateur avec l'ID {$userId} non trouvé");
            return Command::FAILURE;
        }

        $io->title("Scraping des couvertures pour l'utilisateur: {$user->getUsername()}");

        // Récupérer tous les livres de l'utilisateur avec ISBN
        $qb = $this->em->createQueryBuilder();
        $qb->select('l')
            ->from(Livre::class, 'l')
            ->join('l.listeUser', 'ul')
            ->where('ul.user = :user')
            ->andWhere('l.isbn IS NOT NULL and l.isbn != :empty1')
            ->setParameter('user', $user)
            ->setParameter('empty1', '');

        if (!$force) {
            $qb->andWhere('(l.image2 IS NULL OR l.image2 = :empty2)')
                ->setParameter('empty2', '');
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $livres = $qb->getQuery()->getResult();

        $total = count($livres);
        $io->note("Nombre de livres à traiter: {$total}");

        if ($total === 0) {
            $io->success('Aucun livre à traiter');
            return Command::SUCCESS;
        }

        $uploadDir = $this->projectDir . '/public/uploads/covers';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            $io->note("Répertoire créé: {$uploadDir}");
        }

        $processed = 0;
        $success = 0;
        $failed = 0;

        $io->progressStart($total);

        foreach ($livres as $livre) {
            $processed++;
            
            $io->newLine();
            $io->section("Livre {$processed}/{$total}: {$livre->getTitre()} (ISBN: {$livre->getIsbn()})");

            try {
                // Scraper toutes les sources
                $images = $this->bookCoverService->findAllCovers($livre->getIsbn());

                if (empty($images)) {
                    $io->warning('Aucune image trouvée');
                    $failed++;
                    $io->progressAdvance();
                    
                    if ($processed < $total) {
                        $io->note("Pause de {$delay} secondes...");
                        sleep($delay);
                    }
                    continue;
                }

                // Priorité: Amazon > autres sources
                $selectedImage = null;
                foreach ($images as $image) {
                    if ($image['source'] === 'Amazon') {
                        $selectedImage = $image;
                        break;
                    }
                }

                // Si pas d'image Amazon, prendre la première disponible
                if (!$selectedImage) {
                    $selectedImage = $images[0];
                }

                $io->text("Image sélectionnée: {$selectedImage['source']} - {$selectedImage['url']}");

                // Télécharger l'image
                $imageContent = @file_get_contents($selectedImage['url']);
                
                if ($imageContent === false) {
                    $io->error('Impossible de télécharger l\'image');
                    $failed++;
                    $io->progressAdvance();
                    
                    if ($processed < $total) {
                        $io->note("Pause de {$delay} secondes...");
                        sleep($delay);
                    }
                    continue;
                }

                // Générer un nom de fichier unique
                $extension = pathinfo(parse_url($selectedImage['url'], PHP_URL_PATH), PATHINFO_EXTENSION);
                if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $extension = 'jpg';
                }
                
                $filename = $livre->getId() . '_' . uniqid() . '.' . $extension;
                $filepath = $uploadDir . '/' . $filename;

                // Sauvegarder l'image
                if (file_put_contents($filepath, $imageContent) === false) {
                    $io->error('Impossible de sauvegarder l\'image');
                    $failed++;
                    $io->progressAdvance();
                    
                    if ($processed < $total) {
                        $io->note("Pause de {$delay} secondes...");
                        sleep($delay);
                    }
                    continue;
                }

                // Supprimer l'ancienne image si elle existe
                if ($livre->getImage2()) {
                    $oldFile = $uploadDir . '/' . $livre->getImage2();
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                // Mettre à jour le livre
                $livre->setImage2($filename);
                $this->em->flush();

                $io->success("Image sauvegardée: {$filename}");
                $success++;

            } catch (\Exception $e) {
                $io->error("Erreur: {$e->getMessage()}");
                $failed++;
            }

            $io->progressAdvance();

            // Pause entre chaque livre pour éviter les blocages
            if ($processed < $total) {
                $io->note("Pause de {$delay} secondes...");
                sleep($delay);
            }
        }

        $io->progressFinish();

        $io->newLine(2);
        $io->success([
            "Scraping terminé!",
            "Total: {$total}",
            "Succès: {$success}",
            "Échecs: {$failed}"
        ]);

        return Command::SUCCESS;
    }
}
