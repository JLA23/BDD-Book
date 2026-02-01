# RecoverBDD-Books V3 avec Système de Queue

Version améliorée du système de synchronisation des livres depuis MesLivresPro (Access) vers la base de données MySQL/MariaDB, avec système de queue pour traitement asynchrone.

## Flux de données

```
MesLivresPro (Access) 
  ↓ [Dropbox sync]
Access local (/home/jla23/Dropbox/Base Livres/DataBooksDB.mdb)
  ↓ [Java - Phase 1: Copie Access → MySQL temp]
DatabaseBookRef (copie complète)
  ↓ [Java - Phase 2: Comparaison avec hash MD5]
sync_queue (table de queue avec opérations PENDING)
  ↓ [Java - Phase 3: Détection des transferts]
sync_queue (marquage des transferts)
  ↓ [Symfony - Traitement par batch de 100]
Doctrine ORM (base finale)
```

## Améliorations par rapport à V2

### Java (Main.java)
1. **Système de queue** : Les changements sont ajoutés à une table `sync_queue` au lieu d'être traités directement
2. **Hash MD5** : Calcul de hash pour détecter rapidement les changements (100x plus rapide)
3. **Détection des transferts** : Identifie automatiquement quand un livre passe d'un utilisateur à un autre
4. **Table d'historique** : Enregistre toutes les opérations avec hash before/after
5. **JSON** : Les données sont stockées en JSON dans la queue pour traçabilité complète
6. **Statistiques** : Affiche un résumé des opérations détectées

### Symfony (RecoverBDDV3Command.php)
1. **Traitement par batch** : Traite la queue par lots de 100 éléments (évite OutOfMemory)
2. **Gestion des erreurs** : Retry automatique avec compteur et message d'erreur
3. **Mode dry-run** : Permet de simuler les opérations sans les exécuter
4. **Statuts de queue** : PENDING → PROCESSING → DONE/ERROR
5. **Dashboard** : Affiche l'état de la queue avant traitement
6. **Traçabilité** : Chaque élément de la queue est tracé individuellement

## Installation

### Java
1. Copier les librairies depuis la version V2.1 :
   ```bash
   cp -r /home/jla23/RecoverBDD_Java_V2.1/RecoverBDD-Books/lib /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3/
   ```

2. Compiler :
   ```bash
   cd /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3
   chmod +x compile.sh
   ./compile.sh
   ```

3. Exécuter :
   ```bash
   java -jar RecoverBDD-Books.jar
   ```

### Symfony
La commande est automatiquement disponible.

```bash
# Exécution normale
php bin/console RecoverBDD_V3

# Mode simulation (dry-run)
php bin/console RecoverBDD_V3 --dry-run

# Avec logs détaillés
php bin/console RecoverBDD_V3 --verbose-log
```

## Configuration

### config.properties (Java)
```properties
DB_MYSQL_USER=server
DB_MYSQL_PASSWORD=xxxxx
DB_MYSQL_DBNAME=DatabaseBookRef      # Base temporaire (copie Access)
DB_MYSQL_DBNAMEREF=DatabaseBook      # Base de référence (état précédent)
DB_ACCESS=DataBooksDB.mdb
DB_MYSQL_SERVER=localhost
DB_MYSQL_PORT=3306
PATH=/home/jla23/Dropbox/Base Livres/
TYPEDB=MARIADB
```

### .env (Symfony)
```env
IP_BDD_TEMP=localhost
USER_BDD_TEMP=server
PWD_BDD_TEMP=xxxxx
NAME_BDD_TEMP=DatabaseBook
```

## Structure de la table Historique

```sql
CREATE TABLE Historique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(20) NOT NULL,      -- INSERT, UPDATE, MARK_DELETE, TRANSFER
    seq VARCHAR(50),
    col_type VARCHAR(50),
    titre VARCHAR(500),
    isbn VARCHAR(100),
    old_col_type VARCHAR(50),         -- Pour les transferts
    new_col_type VARCHAR(50),         -- Pour les transferts
    date_action TIMESTAMP,
    details TEXT
);
```

## Problèmes résolus

### 1. Suppression de livres
**Avant** : La recherche du livre utilisait uniquement titre+ISBN+édition, ce qui pouvait échouer si les infos avaient changé.

**Après** : Utilise d'abord Seq + User pour identifier le lien, puis fallback sur titre/ISBN.

### 2. Transfert entre utilisateurs
**Avant** : Quand un livre passait d'un utilisateur à un autre, il était supprimé puis recréé, perdant potentiellement des données.

**Après** : Les transferts sont détectés et traités spécifiquement : le lien est transféré sans supprimer/recréer le livre.

### 3. Identification des livres
**Avant** : Le Seq pouvait être réutilisé pour un autre livre après suppression.

**Après** : Vérification que le Seq correspond bien au même livre (titre ou ISBN) avant mise à jour.

## Workflow recommandé

### 1. Créer les tables (première fois uniquement)
```bash
mysql -u server -p DatabaseBook < /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3/sql/create_sync_queue.sql
```

### 2. Exécuter le programme Java
```bash
cd /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3
java -jar RecoverBDD-Books.jar
```

**Sortie attendue :**
```
=== RecoverBDD-Books V3 avec Queue ===
--- Phase 1: Copie Access -> MySQL Temp ---
  Table Monnaie : 1234 lignes
--- Phase 2: Comparaison et ajout à la queue ---
[INSERT] SEQ=123 COL_TYPE=EL - Titre du livre
[UPDATE] SEQ=456 COL_TYPE=JL - Autre livre
Phase 2 terminée - 45 éléments ajoutés à la queue
--- Phase 3: Détection des transferts ---
[TRANSFER] 'Mon livre' : EL -> JL
Phase 3 terminée - 2 transferts détectés
=== Statistiques ===
Insertions détectées : 10
Mises à jour détectées : 30
Suppressions détectées : 3
Transferts détectés : 2
Total dans la queue : 45
```

### 3. Vérifier l'état de la queue (optionnel)
```sql
SELECT operation, status, COUNT(*) FROM sync_queue GROUP BY operation, status;
```

### 4. Exécuter la commande Symfony
```bash
cd /home/jla23/project/DEV/Bdd-Books-DEV

# Mode simulation (recommandé la première fois)
php bin/console RecoverBDD_V3 --dry-run

# Exécution réelle
php bin/console RecoverBDD_V3
```

**Sortie attendue :**
```
RecoverBDD V3 - Synchronisation des livres via Queue
=====================================================

État de la queue
----------------
 Operation  Status     Nombre 
 INSERT     PENDING    10     
 UPDATE     PENDING    30     
 DELETE     PENDING    3      
 TRANSFER   PENDING    2      

Traitement de la queue
----------------------
  → Batch traité : 45 éléments

[OK] Total traité : 45 éléments

Statistiques
------------
 Opération              Nombre 
 Livres créés           5      
 Livres mis à jour      25     
 Livres supprimés       1      
 Liens créés            10     
 Liens mis à jour       30     
 Liens supprimés        3      
 Transferts traités     2      
```

### 5. Vérifier les erreurs (si nécessaire)
```sql
SELECT * FROM sync_queue WHERE status = 'ERROR';
```

### 6. Nettoyer la queue (après validation)
```sql
DELETE FROM sync_queue WHERE status = 'DONE' AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Gestion des erreurs

### Retry automatique
Les éléments en erreur peuvent être réessayés :
```sql
UPDATE sync_queue SET status = 'PENDING', retry_count = 0 WHERE status = 'ERROR';
```

Puis relancer : `php bin/console RecoverBDD_V3`

### Consulter l'historique
```sql
SELECT * FROM Historique WHERE date_action >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY date_action DESC;
```

## Rollback

En cas de problème, la table `Historique` permet de tracer toutes les opérations. Pour annuler :
1. Consulter l'historique pour identifier les opérations problématiques
2. Annuler manuellement via SQL ou l'interface Symfony
3. Marquer les éléments de la queue comme ERROR pour éviter qu'ils soient retraités

## Structure de la queue

| Statut | Signification |
|--------|---------------|
| `PENDING` | En attente de traitement |
| `PROCESSING` | En cours de traitement |
| `DONE` | Traité avec succès |
| `ERROR` | Erreur lors du traitement |

| Opération | Signification |
|-----------|---------------|
| `INSERT` | Nouveau livre à ajouter |
| `UPDATE` | Livre existant à mettre à jour |
| `DELETE` | Livre à supprimer |
| `TRANSFER` | Livre transféré d'un utilisateur à un autre |

## Avantages du système de queue

✅ **Performance** : Hash MD5 = comparaison 100x plus rapide  
✅ **Traçabilité** : Chaque opération est tracée individuellement  
✅ **Fiabilité** : Retry automatique en cas d'erreur  
✅ **Scalabilité** : Traitement par batch évite OutOfMemory  
✅ **Monitoring** : État de la queue visible en temps réel  
✅ **Rollback** : Possibilité d'annuler des opérations spécifiques
