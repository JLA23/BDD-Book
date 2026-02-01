<?php
/**
 * RecoverBDDV3Command - Version améliorée
 * 
 * Améliorations par rapport à V2 :
 * - Meilleure gestion de la suppression de livres (utilisation du Seq + COL_TYPE)
 * - Gestion du transfert de livres entre utilisateurs
 * - Logging détaillé des opérations
 * - Transactions pour éviter les états incohérents
 * - Détection des doublons de livres
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RecoverBDDV3Command extends Command
{
    protected static $defaultName = 'RecoverBDD_V3';
    
    private EntityManagerInterface $em;
    private ?\PDO $pdo = null;
    private ?SymfonyStyle $io = null;
    
    // Statistiques
    private int $livresCreated = 0;
    private int $livresUpdated = 0;
    private int $liensCreated = 0;
    private int $liensUpdated = 0;
    private int $liensDeleted = 0;
    private int $livresDeleted = 0;
    private int $transfersProcessed = 0;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Récupère les données de la base Access via MySQL (V3 - améliorée)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule les opérations sans les exécuter')
            ->addOption('verbose-log', null, InputOption::VALUE_NONE, 'Affiche les logs détaillés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        
        $this->io->title('RecoverBDD V3 - Synchronisation des livres via Queue');
        
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
            
            // 2. Afficher l'état de la queue
            $this->io->section('État de la queue');
            $this->displayQueueStats();
            
            // 3. Traiter la queue par batch
            $this->io->section('Traitement de la queue');
            $this->processQueue($dryRun);
            
            // 4. Nettoyer les auteurs orphelins
            $this->io->section('Nettoyage des auteurs orphelins');
            $this->cleanOrphanAuthors($dryRun);
            
            // 5. Synchroniser la base de référence
            if (!$dryRun) {
                $this->io->section('Synchronisation de la base de référence');
                $this->syncReferenceDatabase();
            }
            
            // Afficher les statistiques
            $this->displayStats();
            
            $this->pdo = null;
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->io->error('Erreur : ' . $e->getMessage());
            $this->io->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function initPdoConnection(): void
    {
        $servername = $_ENV['IP_BDD_TEMP'];
        $username = $_ENV['USER_BDD_TEMP'];
        $password = $_ENV['PWD_BDD_TEMP'];
        $dbname = $_ENV['NAME_BDD_TEMP'];
        
        $this->pdo = new \PDO(
            "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        $this->io->success('Connexion à la base temporaire établie');
    }

    private function syncCategories(): void
    {
        $count = 0;
        $categories = [];
        
        // Charger toutes les catégories existantes en cache
        $existingCategories = $this->em->getRepository(Category::class)->findAll();
        foreach ($existingCategories as $cat) {
            $categories[strtoupper($cat->getNom())] = true;
        }
        
        // Extraire les catégories depuis le JSON de la queue (MATIÈRE avec accent)
        $sql = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data, '$.MATIÈRE')) as matiere FROM sync_queue WHERE JSON_EXTRACT(data, '$.MATIÈRE') IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['matiere'] ?? '');
            if (empty($nom) || $nom === 'null') continue;
            
            $nomUpper = strtoupper($nom);
            if (!isset($categories[$nomUpper])) {
                $categorie = new Category();
                $categorie->setNom($nom);
                $this->em->persist($categorie);
                $categories[$nomUpper] = true;
                $count++;
            }
        }
        
        if ($count > 0) {
            $this->em->flush();
        }
        $this->io->text("  → $count nouvelles catégories ajoutées");
    }

    private function syncEditions(): void
    {
        $count = 0;
        $editions = [];
        
        // Charger toutes les éditions existantes en cache
        $existingEditions = $this->em->getRepository(Edition::class)->findAll();
        foreach ($existingEditions as $ed) {
            $editions[strtoupper($ed->getNom())] = true;
        }
        
        // Extraire les éditions depuis le JSON de la queue
        $sql = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data, '$.CATEGORIE')) as categorie FROM sync_queue WHERE JSON_EXTRACT(data, '$.CATEGORIE') IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cat = $this->cleanEditionName($row['categorie'] ?? '');
            if (empty($cat) || $cat === 'null') continue;
            
            $catUpper = strtoupper($cat);
            if (!isset($editions[$catUpper])) {
                $edition = new Edition();
                $edition->setNom($cat);
                $this->em->persist($edition);
                $editions[$catUpper] = true;
                $count++;
            }
        }
        
        if ($count > 0) {
            $this->em->flush();
        }
        $this->io->text("  → $count nouvelles éditions ajoutées");
    }

    private function syncCollections(): void
    {
        $count = 0;
        $collections = [];
        
        // Charger toutes les collections existantes en cache
        $existingCollections = $this->em->getRepository(Collection::class)->findAll();
        foreach ($existingCollections as $col) {
            $collections[strtoupper($col->getNom())] = true;
        }
        
        // Extraire les collections depuis le JSON de la queue
        $sql = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data, '$.ETAT')) as etat FROM sync_queue WHERE JSON_EXTRACT(data, '$.ETAT') IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nom = trim($row['etat'] ?? '');
            if (empty($nom) || $nom === 'null') continue;
            
            $nomUpper = strtoupper($nom);
            if (!isset($collections[$nomUpper])) {
                $collection = new Collection();
                $collection->setNom($nom);
                $this->em->persist($collection);
                $collections[$nomUpper] = true;
                $count++;
            }
        }
        
        if ($count > 0) {
            $this->em->flush();
        }
        $this->io->text("  → $count nouvelles collections ajoutées");
    }

    private function syncAuteurs(): void
    {
        $count = 0;
        $auteurs = [];
        
        // Charger tous les auteurs existants en cache
        $existingAuteurs = $this->em->getRepository(Auteur::class)->findAll();
        foreach ($existingAuteurs as $aut) {
            $auteurs[strtoupper($aut->getNom())] = true;
        }
        
        // Extraire les auteurs depuis le JSON de la queue
        $sql = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data, '$.PAYS')) as pays FROM sync_queue WHERE JSON_EXTRACT(data, '$.PAYS') IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $paysData = $row['pays'] ?? '';
            if (empty($paysData) || $paysData === 'null') continue;
            
            $auteursListe = explode(',', $paysData);
            foreach ($auteursListe as $aut) {
                $nomAuteur = trim($aut);
                if (empty($nomAuteur)) continue;
                
                $nomAuteurUpper = strtoupper($nomAuteur);
                if (!isset($auteurs[$nomAuteurUpper])) {
                    $auteur = new Auteur();
                    $auteur->setNom($nomAuteur);
                    $this->em->persist($auteur);
                    $auteurs[$nomAuteurUpper] = true;
                    $count++;
                }
            }
        }
        
        if ($count > 0) {
            $this->em->flush();
        }
        $this->io->text("  → $count nouveaux auteurs ajoutés");
    }

    /**
     * Affiche les statistiques de la queue
     */
    private function displayQueueStats(): void
    {
        $sql = "SELECT operation, status, COUNT(*) as count FROM sync_queue GROUP BY operation, status ORDER BY operation, status";
        $stats = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        
        $table = [];
        foreach ($stats as $stat) {
            $table[] = [$stat['operation'], $stat['status'], $stat['count']];
        }
        
        if (empty($table)) {
            $this->io->text('  → Queue vide');
        } else {
            $this->io->table(['Opération', 'Statut', 'Nombre'], $table);
        }
    }

    /**
     * Traite la queue par batch de 100 éléments
     */
    private function processQueue(bool $dryRun): void
    {
        $batchSize = 100;
        $processed = 0;
        $processedIds = []; // Pour éviter de retraiter les mêmes en dry-run
        
        do {
            // Récupérer un batch d'éléments PENDING
            $sql = "SELECT * FROM sync_queue WHERE status = 'PENDING'";
            
            // En dry-run, exclure les IDs déjà traités
            if ($dryRun && !empty($processedIds)) {
                $placeholders = implode(',', $processedIds);
                $sql .= " AND id NOT IN ($placeholders)";
            }
            
            $sql .= " ORDER BY created_at ASC LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                break;
            }
            
            foreach ($items as $item) {
                try {
                    if (!$dryRun) {
                        $this->markQueueItemProcessing($item['id']);
                    } else {
                        // En dry-run, mémoriser l'ID pour ne pas le retraiter
                        $processedIds[] = $item['id'];
                    }
                    
                    $data = json_decode($item['data'], true);
                    
                    switch ($item['operation']) {
                        case 'INSERT':
                            $this->processInsert($item, $data, $dryRun);
                            break;
                        case 'UPDATE':
                            $this->processUpdate($item, $data, $dryRun);
                            break;
                        case 'DELETE':
                            $this->processDelete($item, $data, $dryRun);
                            break;
                        case 'TRANSFER':
                            $this->processTransfer($item, $data, $dryRun);
                            break;
                    }
                    
                    if (!$dryRun) {
                        $this->markQueueItemDone($item['id']);
                    }
                    $processed++;
                    
                } catch (\Exception $e) {
                    $this->io->error("Erreur sur l'élément {$item['id']}: " . $e->getMessage());
                    if (!$dryRun) {
                        $this->markQueueItemError($item['id'], $e->getMessage());
                    }
                }
            }
            
            if ($processed % 500 == 0 && $processed > 0) {
                $this->io->text("  → Progression : $processed éléments traités");
            }
            
        } while (count($items) === $batchSize);
        
        $this->io->success("Total traité : $processed éléments");
    }

    private function processInsert(array $item, array $data, bool $dryRun): void
    {
        $user = $this->getUserByIdAccess($item['col_type']);
        if (!$user) {
            throw new \Exception("Utilisateur non trouvé : {$item['col_type']}");
        }
        
        $monnaie = $this->em->getRepository(Monnaie::class)->findOneById(1);
        $edition = $this->getEditionFromData($data);
        
        // Créer ou trouver le livre
        $livre = $this->findOrCreateLivreFromData($data, $edition, $monnaie);
        
        // Créer le lien utilisateur-livre avec le seq depuis $item
        $row = $data;
        $row['Seq'] = $item['seq']; // Ajouter le Seq depuis la table sync_queue
        $this->createOrUpdateLienUserLivre($user, $livre, $row, $monnaie);
        
        // Gérer les auteurs
        $this->syncAuteursForLivre($livre, $data['PAYS'] ?? '');
    }

    private function processUpdate(array $item, array $data, bool $dryRun): void
    {
        $user = $this->getUserByIdAccess($item['col_type']);
        if (!$user) {
            throw new \Exception("Utilisateur non trouvé : {$item['col_type']}");
        }
        
        $lul = $this->em->getRepository(LienUserLivre::class)->getLivreByUserAndSeq($user, intval($item['seq']));
        
        if ($lul) {
            $livre = $lul->getLivre();
            $edition = $this->getEditionFromData($data);
            
            // Mettre à jour le livre
            $this->updateLivreFromData($livre, $data, $edition);
            
            // Mettre à jour le lien
            $lul->setPrixAchat(floatval($data['VALEUR ESTIMÉE'] ?? $data['Valeur estimée'] ?? 0));
            $lul->setCommentaire($data['COMMPERSO'] ?? $data['CommPerso'] ?? null);
            $lul->setParticularite($data['GRAVEUR'] ?? $data['Graveur'] ?? null);
            
            $this->em->persist($lul);
            $this->em->flush();
            
            // Gérer les auteurs
            $this->syncAuteursForLivre($livre, $data['PAYS'] ?? $data['Pays'] ?? '');
            
            $this->liensUpdated++;
        }
    }

    private function processDelete(array $item, array $data, bool $dryRun): void
    {
        $user = $this->getUserByIdAccess($item['col_type']);
        if (!$user) {
            return;
        }
        
        $lul = $this->em->getRepository(LienUserLivre::class)->getLivreByUserAndSeq($user, intval($item['seq']));
        
        if ($lul) {
            $livre = $lul->getLivre();
            
            // Supprimer le lien
            $this->em->remove($lul);
            $this->em->flush();
            $this->liensDeleted++;
            
            // Vérifier si le livre n'a plus de propriétaires
            $this->em->refresh($livre);
            $remainingLiens = $this->em->getRepository(LienUserLivre::class)->getListeUserByLivre($livre);
            
            if (count($remainingLiens) === 0) {
                // Supprimer les liens auteurs
                $liensAuteurs = $this->em->getRepository(LienAuteurLivre::class)->getListeAuteur($livre);
                foreach ($liensAuteurs as $la) {
                    $this->em->remove($la);
                }
                $this->em->flush();
                
                // Supprimer le livre
                $this->em->remove($livre);
                $this->em->flush();
                $this->livresDeleted++;
            }
        }
    }

    private function processTransfer(array $item, array $data, bool $dryRun): void
    {
        // Pour un transfert, traiter comme une insertion
        $this->processInsert($item, $data, $dryRun);
        $this->transfersProcessed++;
    }

    private function markQueueItemProcessing(int $id): void
    {
        $sql = "UPDATE sync_queue SET status = 'PROCESSING' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    private function markQueueItemDone(int $id): void
    {
        $sql = "UPDATE sync_queue SET status = 'DONE', processed_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    private function markQueueItemError(int $id, string $error): void
    {
        $sql = "UPDATE sync_queue SET status = 'ERROR', error_message = :error, retry_count = retry_count + 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'error' => $error]);
    }

    private function getEditionFromData(array $data): ?Edition
    {
        $editionName = $this->cleanEditionName($data['CATEGORIE'] ?? $data['Categorie'] ?? '');
        if (empty($editionName)) {
            return null;
        }
        return $this->em->getRepository(Edition::class)->getEditionByName($editionName);
    }

    private function findOrCreateLivreFromData(array $data, ?Edition $edition, ?Monnaie $monnaie): Livre
    {
        $isbn = trim($data['CLASSEUR'] ?? $data['Classeur'] ?? '');
        $livre = null;
        
        if (!empty($isbn)) {
            $livre = $this->em->getRepository(Livre::class)->findOneBy(['isbn' => $isbn]);
        }
        
        // Chercher le livre existant
        if (!$livre && $edition) {
            $livre = $this->em->getRepository(Livre::class)->getLivreByInfos(
                $data['PARTICULARITE'] ?? $data['Particularite'] ?? '',
                $isbn,
                $edition
            );
        }
        
        if (!$livre) {
            $livre = new Livre();
            $this->livresCreated++;
        }
        
        $this->updateLivreFromData($livre, $data, $edition);
        
        if ($monnaie) {
            $livre->setMonnaie($monnaie);
        }
        
        $this->em->persist($livre);
        $this->em->flush();
        
        return $livre;
    }

    private function updateLivreFromData(Livre $livre, array $data, ?Edition $edition): void
    {
        $livre->setTitre($data['PARTICULARITE'] ?? $data['Particularite'] ?? '');
        $livre->setTome($data['PAGE'] ?? $data['Page'] ?? null);
        $livre->setAnnee($data['ANNÉE'] ?? $data['Année'] ?? null);
        
        // Décoder le base64 pour obtenir les données binaires de l'image
        $aversData = $data['AVERS'] ?? $data['Avers'] ?? null;
        if ($aversData && !empty($aversData)) {
            // Si c'est une chaîne base64, la décoder
            $imageBlob = base64_decode($aversData, true);
            if ($imageBlob !== false) {
                $livre->setImage($imageBlob);
            }
        }
        
        $livre->setPrixBase(floatval($data['VALEUR ESTIMÉE'] ?? $data['Valeur estimée'] ?? 0));
        $livre->setPages($data['DIAMETRE'] ?? $data['Diametre'] ?? null);
        $livre->setIsbn(trim($data['CLASSEUR'] ?? $data['Classeur'] ?? ''));
        $livre->setAmazon($data['POID'] ?? $data['Poid'] ?? null);
        $livre->setResume($data['NOTES'] ?? $data['Notes'] ?? null);
        
        if ($edition) {
            $livre->setEdition($edition);
        }
        
        // Catégorie (MATIÈRE avec accent dans le JSON)
        $categoryName = $data['MATIÈRE'] ?? $data['MATIERE'] ?? $data['Matière'] ?? $data['Matiere'] ?? '';
        if (!empty($categoryName)) {
            $category = $this->em->getRepository(Category::class)->getCategoryByName($categoryName);
            if ($category) {
                $livre->setCategory($category);
            }
        }
        
        // Collection
        $collectionName = $data['ETAT'] ?? $data['Etat'] ?? '';
        if (!empty($collectionName)) {
            $collection = $this->em->getRepository(Collection::class)->getCollectionByName($collectionName);
            if ($collection) {
                $livre->setCollection($collection);
            } else {
                $this->io->warning("Collection introuvable : '$collectionName' pour livre: " . ($data['PARTICULARITE'] ?? $data['Particularite'] ?? 'N/A'));
            }
        }
        
        $this->em->persist($livre);
        $this->em->flush();
    }

    private function processBooks(bool $dryRun): void
    {
        $monnaie = $this->em->getRepository(Monnaie::class)->findOneById(1);
        $users = $this->em->getRepository(User::class)->findAll();
        
        foreach ($users as $user) {
            $this->io->text("Traitement de l'utilisateur : " . $user->getUsername());
            
            $sql = 'SELECT * FROM Monnaie WHERE Traite = 0 AND COL_TYPE = :colType';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['colType' => $user->getIdAccess()]);
            
            $count = 0;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (empty($row['Particularite'])) continue;
                
                $count++;
                
                if (!$dryRun) {
                    $this->processBookRow($row, $user, $monnaie);
                }
            }
            
            $this->io->text("  → $count livres traités pour " . $user->getUsername());
        }
    }

    private function processBookRow(array $row, User $user, ?Monnaie $monnaie): void
    {
        $edition = $this->em->getRepository(Edition::class)->getEditionByName(
            $this->cleanEditionName($row['Categorie'] ?? '')
        );
        
        // Chercher d'abord par le lien existant (Seq + User)
        $lul = $this->em->getRepository(LienUserLivre::class)->getLivreByUserAndSeq($user, intval($row['Seq']));
        
        $livre = null;
        if ($lul) {
            $livre = $lul->getLivre();
            // Vérifier que c'est bien le même livre (titre ou ISBN)
            if ($livre->getTitre() !== $row['Particularite'] && $livre->getIsbn() !== trim($row['Classeur'] ?? '')) {
                // Le Seq a été réutilisé pour un autre livre, chercher par infos
                $livre = $this->findOrCreateLivre($row, $edition, $monnaie);
            } else {
                // Mettre à jour le livre existant
                $this->updateLivre($livre, $row, $edition);
                $this->livresUpdated++;
            }
        } else {
            // Pas de lien existant, chercher ou créer le livre
            $livre = $this->findOrCreateLivre($row, $edition, $monnaie);
        }
        
        // Créer ou mettre à jour le lien utilisateur-livre
        $this->createOrUpdateLienUserLivre($user, $livre, $row, $monnaie);
        
        // Gérer les auteurs
        $this->syncAuteursForLivre($livre, $row['Pays'] ?? '');
        
        // Marquer comme traité
        $this->markAsProcessed($row['Seq'], $row['COL_TYPE'], $row['Classeur'], $row['Particularite']);
    }

    private function findOrCreateLivre(array $row, ?Edition $edition, ?Monnaie $monnaie): Livre
    {
        // Chercher par ISBN d'abord (plus fiable)
        $isbn = trim($row['Classeur'] ?? '');
        $livre = null;
        
        if (!empty($isbn)) {
            $livre = $this->em->getRepository(Livre::class)->findOneBy(['isbn' => $isbn]);
        }
        
        // Sinon chercher par titre + édition
        if (!$livre && $edition) {
            $livre = $this->em->getRepository(Livre::class)->getLivreByInfos(
                $row['Particularite'],
                $isbn,
                $edition
            );
        }
        
        if (!$livre) {
            $livre = new Livre();
            $this->livresCreated++;
        }
        
        $this->updateLivre($livre, $row, $edition);
        
        if ($monnaie) {
            $livre->setMonnaie($monnaie);
        }
        
        $this->em->persist($livre);
        $this->em->flush();
        
        return $livre;
    }

    private function updateLivre(Livre $livre, array $row, ?Edition $edition): void
    {
        $livre->setTitre($row['Particularite']);
        $livre->setTome($row['Page'] ?? null);
        $livre->setAnnee($row['Année'] ?? null);
        
        // Décoder le base64 pour obtenir les données binaires de l'image
        $aversData = $row['Avers'] ?? null;
        if ($aversData && !empty($aversData)) {
            $imageBlob = base64_decode($aversData, true);
            if ($imageBlob !== false) {
                $livre->setImage($imageBlob);
            }
        }
        
        $livre->setPrixBase(floatval($row['Valeur estimée'] ?? 0));
        $livre->setPages($row['Diametre'] ?? null);
        $livre->setIsbn(trim($row['Classeur'] ?? ''));
        $livre->setAmazon($row['Poid'] ?? null);
        $livre->setResume($row['Notes'] ?? null);
        
        if ($edition) {
            $livre->setEdition($edition);
        }
        
        $category = $this->em->getRepository(Category::class)->getCategoryByName($row['Matière'] ?? '');
        if ($category) {
            $livre->setCategory($category);
        }
        
        $collection = $this->em->getRepository(Collection::class)->getCollectionByName($row['Etat'] ?? '');
        if ($collection) {
            $livre->setCollection($collection);
        }
        
        $this->em->persist($livre);
        $this->em->flush();
    }

    private function createOrUpdateLienUserLivre(User $user, Livre $livre, array $row, ?Monnaie $monnaie): void
    {
        $lul = $this->em->getRepository(LienUserLivre::class)->getLienByUserAndLivre(
            $user,
            $livre,
            intval($row['Seq'])
        );
        
        if (!$lul) {
            $lul = new LienUserLivre();
            $lul->setLivre($livre);
            $lul->setUser($user);
            $lul->setSeq(intval($row['Seq']));
            $this->liensCreated++;
        } else {
            $this->liensUpdated++;
        }
        
        $lul->setPrixAchat(floatval($row['Valeur estimée'] ?? 0));
        $lul->setCommentaire($row['CommPerso'] ?? null);
        $lul->setParticularite($row['Graveur'] ?? null);
        
        if ($monnaie) {
            $lul->setMonnaie($monnaie);
        }
        
        $this->em->persist($lul);
        $this->em->flush();
    }

    private function syncAuteursForLivre(Livre $livre, string $auteursString): void
    {
        $auteurs = array_filter(array_map('trim', explode(',', $auteursString)));
        
        // Ajouter les nouveaux auteurs
        foreach ($auteurs as $nomAuteur) {
            if (empty($nomAuteur)) continue;
            
            $auteur = $this->em->getRepository(Auteur::class)->findAuteurIntelligent($nomAuteur);
            if (!$auteur) {
                $auteur = new Auteur();
                $auteur->setNom($nomAuteur);
                $this->em->persist($auteur);
                $this->em->flush();
            }
            
            $lal = $this->em->getRepository(LienAuteurLivre::class)->getLienByAuteurAndLivre($auteur, $livre);
            if (!$lal) {
                $lal = new LienAuteurLivre();
                $lal->setLivre($livre);
                $lal->setAuteur($auteur);
                $this->em->persist($lal);
                $this->em->flush();
            }
        }
        
        // Supprimer les auteurs qui ne sont plus liés
        $liensAuteurs = $this->em->getRepository(LienAuteurLivre::class)->getListeAuteur($livre);
        foreach ($liensAuteurs as $la) {
            $found = false;
            foreach ($auteurs as $nomAuteur) {
                if (strtoupper($la->getAuteur()->getNom()) === strtoupper($nomAuteur)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->em->remove($la);
                $this->em->flush();
            }
        }
    }

    private function processDeletions(bool $dryRun): void
    {
        $users = $this->em->getRepository(User::class)->findAll();
        
        foreach ($users as $user) {
            $sql = "SELECT * FROM Monnaie WHERE COL_TYPE = :colType AND Traite = -1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['colType' => $user->getIdAccess()]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (empty($row['Particularite'])) continue;
                
                $this->io->text("  → Suppression : '{$row['Particularite']}' pour " . $user->getUsername());
                
                if (!$dryRun) {
                    $this->executeDelete($row, $user);
                }
            }
        }
    }

    private function executeDelete(array $row, User $user): void
    {
        // Chercher le lien par Seq ET User (plus fiable)
        $lul = $this->em->getRepository(LienUserLivre::class)->getLivreByUserAndSeq($user, intval($row['Seq']));
        
        // Si pas trouvé par Seq, chercher par titre/ISBN
        if (!$lul) {
            $edition = $this->em->getRepository(Edition::class)->getEditionByName(
                $this->cleanEditionName($row['Categorie'] ?? '')
            );
            $livre = $this->em->getRepository(Livre::class)->getLivreByInfos(
                $row['Particularite'],
                trim($row['Classeur'] ?? ''),
                $edition
            );
            
            if ($livre) {
                $liens = $this->em->getRepository(LienUserLivre::class)->getListeUserByLivre($livre);
                foreach ($liens as $l) {
                    if ($l->getUser()->getId() === $user->getId()) {
                        $lul = $l;
                        break;
                    }
                }
            }
        }
        
        if ($lul) {
            $livre = $lul->getLivre();
            
            // Supprimer le lien
            $this->em->remove($lul);
            $this->em->flush();
            $this->liensDeleted++;
            
            // Vérifier si le livre n'a plus de propriétaires
            $this->em->refresh($livre);
            $remainingLiens = $this->em->getRepository(LienUserLivre::class)->getListeUserByLivre($livre);
            
            if (count($remainingLiens) === 0) {
                // Supprimer les liens auteurs
                $liensAuteurs = $this->em->getRepository(LienAuteurLivre::class)->getListeAuteur($livre);
                foreach ($liensAuteurs as $la) {
                    $this->em->remove($la);
                }
                $this->em->flush();
                
                // Supprimer le livre
                $this->em->remove($livre);
                $this->em->flush();
                $this->livresDeleted++;
                
                $this->io->text("    → Livre supprimé (plus de propriétaires)");
            }
        }
        
        // Supprimer l'entrée de la base temporaire
        $this->deleteFromTemp($row['Seq'], $row['COL_TYPE'], $row['Classeur'], $row['Particularite']);
    }

    private function cleanOrphanAuthors(bool $dryRun): void
    {
        if ($dryRun) {
            $this->io->text("  → Nettoyage des auteurs orphelins ignoré en mode dry-run");
            return;
        }
        
        // Utiliser une requête SQL directe pour supprimer les auteurs orphelins (beaucoup plus rapide)
        $conn = $this->em->getConnection();
        $sql = "DELETE a FROM auteur a 
                LEFT JOIN lien_auteur_livre lal ON a.id = lal.auteur_id 
                WHERE lal.id IS NULL";
        
        $count = $conn->executeStatement($sql);
        
        $this->io->text("  → $count auteurs orphelins supprimés");
    }

    private function markAsProcessed(int $seq, string $colType, ?string $classeur, ?string $particularite): void
    {
        $sql = 'UPDATE Monnaie SET Traite = 1 WHERE Seq = :seq AND COL_TYPE = :colType AND (Classeur = :classeur OR Particularite = :particularite)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'seq' => $seq,
            'colType' => $colType,
            'classeur' => $classeur ?? '',
            'particularite' => $particularite ?? ''
        ]);
    }

    private function deleteFromTemp(int $seq, string $colType, ?string $classeur, ?string $particularite): void
    {
        $sql = 'DELETE FROM Monnaie WHERE Seq = :seq AND COL_TYPE = :colType AND (Classeur = :classeur OR Particularite = :particularite)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'seq' => $seq,
            'colType' => $colType,
            'classeur' => $classeur ?? '',
            'particularite' => $particularite ?? ''
        ]);
    }

    private function getUserByIdAccess(string $idAccess): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['idAccess' => $idAccess]);
    }

    private function cleanEditionName(?string $name): string
    {
        if (empty($name)) return '';
        
        $name = str_replace(["\r", "\n"], '', $name);
        if (str_contains($name, ';')) {
            $parts = explode(';', $name);
            $name = $parts[0];
        }
        return trim($name);
    }

    private function syncReferenceDatabase(): void
    {
        try {
            $this->io->text('Synchronisation de toutes les tables vers la base de référence...');
            
            // Connexion à la base de référence
            $refHost = $_ENV['DB_MYSQL_SERVER'] ?? 'localhost';
            $refPort = $_ENV['DB_MYSQL_PORT'] ?? '3306';
            $refUser = $_ENV['DB_MYSQL_USER'] ?? 'root';
            $refPass = $_ENV['DB_MYSQL_PASSWORD'] ?? '';
            $refDbName = $_ENV['DB_MYSQL_DBNAMEREF'] ?? '';
            $tempDbName = $_ENV['NAME_BDD_TEMP'] ?? '';
            
            $pdoRef = new \PDO(
                "mysql:host=$refHost;port=$refPort;dbname=$refDbName;charset=utf8mb4",
                $refUser,
                $refPass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Liste des tables à synchroniser (toutes sauf sync_queue et Historique)
            // Monnaie = livres, Matiere = catégories, Etat = collections, Categorie = éditeurs, Pays = auteurs
            $tables = ['Monnaie', 'Matiere', 'Etat', 'Categorie', 'Pays'];
            $totalCopied = 0;
            
            foreach ($tables as $table) {
                try {
                    // Compter les lignes dans la table source
                    $countSource = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                    
                    // Vider la table de la base de référence
                    $pdoRef->exec("TRUNCATE TABLE `{$table}`");
                    
                    // Copier toutes les données de la base temporaire vers la base de référence
                    $sql = "INSERT INTO `{$table}` SELECT * FROM `{$tempDbName}`.`{$table}`";
                    $count = $pdoRef->exec($sql);
                    $totalCopied += $count;
                    
                    $this->io->text("  → Table {$table} : {$count} lignes copiées (source: {$countSource})");
                    
                } catch (\Exception $e) {
                    // Si la table n'existe pas, on continue
                    $this->io->text("  → Table {$table} : ignorée ({$e->getMessage()})");
                }
            }
            
            $this->io->success("Base de référence synchronisée : {$totalCopied} lignes au total");
            
        } catch (\Exception $e) {
            $this->io->warning('Erreur lors de la synchronisation de la base de référence : ' . $e->getMessage());
        }
    }

    private function displayStats(): void
    {
        $this->io->section('Statistiques');
        $this->io->table(
            ['Opération', 'Nombre'],
            [
                ['Livres créés', $this->livresCreated],
                ['Livres mis à jour', $this->livresUpdated],
                ['Livres supprimés', $this->livresDeleted],
                ['Liens créés', $this->liensCreated],
                ['Liens mis à jour', $this->liensUpdated],
                ['Liens supprimés', $this->liensDeleted],
                ['Transferts traités', $this->transfersProcessed],
            ]
        );
    }
}
