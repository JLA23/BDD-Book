<?php
/**
 * SyncFromRefCommand - Synchronise DatabaseBookRef (Java) vers BDD-Book (Symfony)
 * 
 * Lit les données de DatabaseBookRef (mise à jour par Java V2.1) et applique les changements dans BDD-Book
 * Gère correctement les suppressions de liens user-livre (Traite = -1)
 */

namespace App\Command;

use App\Entity\Auteur;
use App\Entity\Category;
use App\Entity\Edition;
use App\Entity\LienAuteurLivre;
use App\Entity\LienUserLivre;
use App\Entity\Livre;
use App\Entity\Collection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncFromRefCommand extends Command
{
    protected static $defaultName = 'sync:from-ref';
    
    private EntityManagerInterface $em;
    private ?\PDO $pdoRef = null;
    private ?SymfonyStyle $io = null;
    
    // Cache pour éviter les requêtes répétées
    private array $categoriesCache = [];
    private array $editionsCache = [];
    private array $collectionsCache = [];
    private array $auteursCache = [];
    private array $usersCache = [];
    
    // Mapping COL_TYPE -> username
    private array $colTypeMapping = [
        'Eric' => 'JLA23',
        'Marie' => 'Naukogha',
        'Parents' => 'JML',
    ];
    
    // Statistiques
    private int $livresCreated = 0;
    private int $livresUpdated = 0;
    private int $liensCreated = 0;
    private int $liensUpdated = 0;
    private int $liensDeleted = 0;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Synchronise DatabaseBookRef (Java) vers BDD-Book (Symfony)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule les opérations sans les exécuter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        
        $this->io->title('Synchronisation DatabaseBookRef → BDD-Book');
        
        if ($dryRun) {
            $this->io->warning('Mode DRY-RUN activé - Aucune modification ne sera effectuée');
        }

        try {
            $this->initPdoConnection();
            
            // 1. Synchroniser les données de référence
            $this->io->section('Synchronisation des données de référence');
            $this->syncCategories();
            $this->syncEditions();
            $this->syncCollections();
            $this->syncAuteurs();
            
            // 2. Synchroniser les livres et liens
            $this->io->section('Synchronisation des livres');
            $this->syncLivres($dryRun);
            
            // 3. Afficher les statistiques
            $this->displayStats();
            
            $this->io->success('Synchronisation terminée avec succès');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->io->error('Erreur : ' . $e->getMessage());
            $this->io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function initPdoConnection(): void
    {
        $host = $_ENV['DB_MYSQL_SERVER'] ?? 'localhost';
        $port = $_ENV['DB_MYSQL_PORT'] ?? '3306';
        $user = $_ENV['DB_MYSQL_USER'] ?? 'root';
        $pass = $_ENV['DB_MYSQL_PASSWORD'] ?? '';
        $dbName = $_ENV['DB_MYSQL_DBNAMEREF'] ?? 'DatabaseBookRef';
        
        $this->pdoRef = new \PDO(
            "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        $this->io->success('Connexion à DatabaseBookRef établie');
    }

    private function syncCategories(): void
    {
        $stmt = $this->pdoRef->query("SELECT DISTINCT Matiere FROM Matiere WHERE Matiere IS NOT NULL AND Matiere != ''");
        $count = 0;
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['Matiere']);
            if (empty($nom)) continue;
            
            $category = $this->em->getRepository(Category::class)->findOneBy(['nom' => $nom]);
            if (!$category) {
                $category = new Category();
                $category->setNom($nom);
                $this->em->persist($category);
                $count++;
            }
            $this->categoriesCache[$nom] = $category;
        }
        
        $this->em->flush();
        $this->io->text("→ $count nouvelles catégories ajoutées");
    }

    private function syncEditions(): void
    {
        $stmt = $this->pdoRef->query("SELECT DISTINCT Categorie FROM Categorie WHERE Categorie IS NOT NULL AND Categorie != ''");
        $count = 0;
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['Categorie']);
            if (empty($nom)) continue;
            
            $edition = $this->em->getRepository(Edition::class)
                ->createQueryBuilder('e')
                ->where('UPPER(e.nom) = :nom')
                ->setParameter('nom', strtoupper($nom))
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();
            
            if (empty($edition)) {
                $edition = new Edition();
                $edition->setNom($nom);
                $this->em->persist($edition);
                $count++;
            } else {
                $edition = $edition[0];
            }
            $this->editionsCache[strtoupper($nom)] = $edition;
        }
        
        $this->em->flush();
        $this->io->text("→ $count nouvelles éditions ajoutées");
    }

    private function syncCollections(): void
    {
        $stmt = $this->pdoRef->query("SELECT DISTINCT Etat FROM Etat WHERE Etat IS NOT NULL AND Etat != ''");
        $count = 0;
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['Etat']);
            if (empty($nom)) continue;
            
            $collection = $this->em->getRepository(Collection::class)
                ->createQueryBuilder('c')
                ->where('UPPER(c.nom) = :nom')
                ->setParameter('nom', strtoupper($nom))
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();
            
            if (empty($collection)) {
                $collection = new Collection();
                $collection->setNom($nom);
                $this->em->persist($collection);
                $count++;
            } else {
                $collection = $collection[0];
            }
            $this->collectionsCache[strtoupper($nom)] = $collection;
        }
        
        $this->em->flush();
        $this->io->text("→ $count nouvelles collections ajoutées");
    }

    private function syncAuteurs(): void
    {
        $stmt = $this->pdoRef->query("SELECT DISTINCT Pays FROM Pays WHERE Pays IS NOT NULL AND Pays != ''");
        $count = 0;
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['Pays']);
            if (empty($nom)) continue;
            
            $auteur = $this->em->getRepository(Auteur::class)
                ->createQueryBuilder('a')
                ->where('UPPER(a.nom) = :nom')
                ->setParameter('nom', strtoupper($nom))
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();
            
            if (empty($auteur)) {
                $auteur = new Auteur();
                $auteur->setNom($nom);
                $this->em->persist($auteur);
                $count++;
            } else {
                $auteur = $auteur[0];
            }
            $this->auteursCache[strtoupper($nom)] = $auteur;
        }
        
        $this->em->flush();
        $this->io->text("→ $count nouveaux auteurs ajoutés");
    }

    private function syncLivres(bool $dryRun): void
    {
        // Récupérer tous les livres de DatabaseBookRef avec Traite != -1 (non supprimés)
        $stmt = $this->pdoRef->query("
            SELECT * FROM Monnaie 
            WHERE SEQ != 0 
            ORDER BY SEQ, COL_TYPE
        ");
        
        $processed = 0;
        $batch = 0;
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Les noms de colonnes peuvent être en minuscules
            $seq = $row['SEQ'] ?? $row['Seq'] ?? $row['seq'] ?? 0;
            $colType = $row['COL_TYPE'] ?? $row['Col_Type'] ?? $row['col_type'] ?? '';
            $traite = (int)($row['Traite'] ?? $row['traite'] ?? 0);
            
            // Si Traite = -1, c'est une suppression logique
            if ($traite === -1) {
                $this->handleDeletion($seq, $colType, $dryRun);
            } else {
                // Sinon, c'est un INSERT ou UPDATE
                $this->handleInsertOrUpdate($row, $dryRun);
            }
            
            $processed++;
            
            // Flush tous les 50 éléments
            if (!$dryRun && $processed % 50 === 0) {
                $this->em->flush();
                $this->em->clear();
                // Vider les caches car les entités ne sont plus gérées par l'EM
                $this->categoriesCache = [];
                $this->editionsCache = [];
                $this->collectionsCache = [];
                $this->auteursCache = [];
                $this->usersCache = [];
                $batch++;
                $this->io->text("  → Batch $batch : $processed éléments traités");
            }
        }
        
        if (!$dryRun) {
            $this->em->flush();
        }
        
        $this->io->text("→ Total : $processed éléments traités");
    }

    private function handleDeletion(int $seq, string $colType, bool $dryRun): void
    {
        // Trouver l'utilisateur
        $user = $this->getUserByColType($colType);
        if (!$user) {
            return;
        }
        
        // Trouver le lien user-livre correspondant
        $lien = $this->em->getRepository(LienUserLivre::class)
            ->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.seq = :seq')
            ->setParameter('user', $user)
            ->setParameter('seq', $seq)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if ($lien) {
            if (!$dryRun) {
                $this->em->remove($lien);
                $this->liensDeleted++;
            }
            $this->io->text("[DELETE] Lien user-livre SEQ=$seq COL_TYPE=$colType supprimé");
        }
    }

    private function handleInsertOrUpdate(array $data, bool $dryRun): void
    {
        $seq = $data['SEQ'] ?? $data['Seq'] ?? $data['seq'] ?? 0;
        $colType = $data['COL_TYPE'] ?? $data['Col_Type'] ?? $data['col_type'] ?? '';
        
        // Trouver ou créer le livre
        $livre = $this->findOrCreateLivre($data, $dryRun);
        if (!$livre) {
            return;
        }
        
        // Trouver ou créer le lien user-livre
        $user = $this->getUserByColType($colType);
        if (!$user) {
            return;
        }
        
        $lien = $this->em->getRepository(LienUserLivre::class)
            ->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.seq = :seq')
            ->setParameter('user', $user)
            ->setParameter('seq', $seq)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$lien) {
            if (!$dryRun) {
                $lien = new LienUserLivre();
                $lien->setUser($user);
                $lien->setLivre($livre);
                $lien->setSeq($seq);
                $this->em->persist($lien);
                $this->liensCreated++;
            }
        } else {
            if (!$dryRun) {
                $lien->setLivre($livre);
                $this->liensUpdated++;
            }
        }
    }

    private function findOrCreateLivre(array $data, bool $dryRun): ?Livre
    {
        $titre = trim($data['Particularite'] ?? $data['particularite'] ?? '');
        $isbn = trim($data['Classeur'] ?? $data['classeur'] ?? '');
        $editionNom = trim($data['Categorie'] ?? $data['categorie'] ?? '');
        
        if (empty($titre)) {
            return null;
        }
        
        // Chercher le livre par ISBN ou titre + édition
        $livre = null;
        if (!empty($isbn)) {
            $livre = $this->em->getRepository(Livre::class)->findOneBy(['isbn' => $isbn]);
        }
        
        if (!$livre && !empty($editionNom)) {
            $edition = $this->editionsCache[strtoupper($editionNom)] ?? null;
            if ($edition) {
                $results = $this->em->getRepository(Livre::class)
                    ->createQueryBuilder('l')
                    ->where('UPPER(l.titre) = :titre')
                    ->andWhere('l.edition = :edition')
                    ->setParameter('titre', strtoupper($titre))
                    ->setParameter('edition', $edition)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getResult();
                $livre = !empty($results) ? $results[0] : null;
            }
        }
        
        $isNew = false;
        if (!$livre) {
            if ($dryRun) {
                return null;
            }
            $livre = new Livre();
            $isNew = true;
        }
        
        // Mettre à jour les données du livre
        if (!$dryRun) {
            $livre->setTitre($titre);
            if (!empty($isbn)) $livre->setIsbn($isbn);
            
            // Edition
            if (!empty($editionNom)) {
                $edition = $this->editionsCache[strtoupper($editionNom)] ?? null;
                if ($edition) $livre->setEdition($edition);
            }
            
            // Collection
            $collectionNom = trim($data['Etat'] ?? $data['etat'] ?? '');
            if (!empty($collectionNom)) {
                $collection = $this->collectionsCache[strtoupper($collectionNom)] ?? null;
                if ($collection) $livre->setCollection($collection);
            }
            
            // Catégorie
            $categoryNom = trim($data['Matiere'] ?? $data['matiere'] ?? '');
            if (!empty($categoryNom)) {
                $category = $this->categoriesCache[$categoryNom] ?? null;
                if ($category) $livre->setCategory($category);
            }
            
            // Autres champs
            $annee = $data['Annee'] ?? $data['annee'] ?? null;
            if (!empty($annee)) $livre->setAnnee((int)$annee);
            
            $tome = $data['Tome'] ?? $data['tome'] ?? null;
            if (!empty($tome)) $livre->setTome((int)$tome);
            
            $numero = $data['Numero'] ?? $data['numero'] ?? null;
            if (!empty($numero)) $livre->setNumero((string)$numero);
            
            // Image
            $image = $data['Image'] ?? $data['image'] ?? null;
            if (!empty($image)) {
                $livre->setImage($image);
            }
            
            $image2 = $data['Image2'] ?? $data['image2'] ?? null;
            if (!empty($image2)) {
                $livre->setImage2($image2);
            }
            
            // Auteurs
            $auteurNom = trim($data['Pays'] ?? $data['pays'] ?? '');
            if (!empty($auteurNom)) {
                $auteur = $this->auteursCache[strtoupper($auteurNom)] ?? null;
                if ($auteur) {
                    // Vérifier si le lien auteur-livre existe déjà
                    $lienAuteur = $this->em->getRepository(LienAuteurLivre::class)
                        ->findOneBy(['livre' => $livre, 'auteur' => $auteur]);
                    
                    if (!$lienAuteur) {
                        $lienAuteur = new LienAuteurLivre();
                        $lienAuteur->setLivre($livre);
                        $lienAuteur->setAuteur($auteur);
                        $this->em->persist($lienAuteur);
                    }
                }
            }
            
            if ($isNew) {
                $this->em->persist($livre);
                $this->livresCreated++;
            } else {
                $this->livresUpdated++;
            }
        }
        
        return $livre;
    }

    private function getUserByColType(string $colType): ?User
    {
        if (isset($this->usersCache[$colType])) {
            return $this->usersCache[$colType];
        }
        
        // Mapper COL_TYPE vers le vrai username
        $username = $this->colTypeMapping[$colType] ?? $colType;
        
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($user) {
            $this->usersCache[$colType] = $user;
        }
        
        return $user;
    }

    private function displayStats(): void
    {
        $this->io->section('Statistiques');
        
        $stats = [
            ['Livres créés', $this->livresCreated],
            ['Livres mis à jour', $this->livresUpdated],
            ['Liens créés', $this->liensCreated],
            ['Liens mis à jour', $this->liensUpdated],
            ['Liens supprimés', $this->liensDeleted],
        ];
        
        $this->io->table(['Opération', 'Nombre'], $stats);
    }
}
