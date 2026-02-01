# Scripts de Purge des Livres

## ⚠️ ATTENTION

Ces scripts suppriment des données de la base de données.  
**Toujours faire une sauvegarde avant !**

## Fichiers disponibles

### 1. `purge_livres.sql` - Purge DatabaseBook uniquement
Script simple pour purger les données livres de DatabaseBook (base Symfony).
Les magazines ne sont **PAS** touchés.

**Utilisation :**
```bash
# Faire une sauvegarde AVANT
mysqldump -u server -p DatabaseBook > backup_$(date +%Y%m%d_%H%M%S).sql

# Exécuter la purge
mysql -u server -p DatabaseBook < sql/purge_livres.sql
```

### 2. `purge_livres_safe.sql` - Purge sécurisée DatabaseBook (RECOMMANDÉ)
Script avec backup automatique et possibilité de restauration.

**Utilisation :**
```bash
mysql -u server -p DatabaseBook < sql/purge_livres_safe.sql
```

**Avantages :**
- ✅ Crée des tables de backup temporaires
- ✅ Affiche l'état avant/après
- ✅ Vérifie que les magazines ne sont pas touchés
- ✅ Permet de restaurer facilement si problème

### 3. `purge_databasebookref.sql` - Purge DatabaseBookRef uniquement
Vide la base temporaire DatabaseBookRef (copie depuis Access).

**Utilisation :**
```bash
mysql -u server -p DatabaseBookRef < sql/purge_databasebookref.sql
```

**Tables vidées :**
- Monnaie, Matiere, Categorie, Etat, Pays, Traitement, Historique

**Note :** Cette base est automatiquement recréée par le programme Java.

### 4. `purge_complete_system.sql` - Purge COMPLÈTE (DatabaseBook + DatabaseBookRef)
Vide les deux bases pour repartir complètement de zéro.

**Utilisation :**
```bash
# Backup complet OBLIGATOIRE
mysqldump -u server -p --databases DatabaseBook DatabaseBookRef > backup_complet_$(date +%Y%m%d_%H%M%S).sql

# Purge complète
mysql -u server -p < sql/purge_complete_system.sql
```

**Ce qui est fait :**
- ✅ Vide DatabaseBook (livres uniquement, magazines préservés)
- ✅ Vide DatabaseBookRef (complètement)
- ✅ Réinitialise tous les ID à 1
- ✅ Nettoie sync_queue et Historique
- ✅ Affiche un rapport complet

## Ce qui est supprimé

| Table | Action |
|-------|--------|
| `livre` | TRUNCATE (tous les livres) |
| `lien_user_livre` | TRUNCATE (tous les liens user-livre) |
| `lien_auteur_livre` | TRUNCATE (tous les liens auteur-livre) |
| `auteur` | DELETE orphelins uniquement |
| `sync_queue` | DELETE (sauf ERROR) |
| `Historique` | DELETE (> 30 jours) |

## Ce qui N'EST PAS touché

| Table | Statut |
|-------|--------|
| `kiosk_collec` | ✅ Préservé |
| `kiosk_num` | ✅ Préservé |
| `lien_kiosk_num_user` | ✅ Préservé |
| `user` | ✅ Préservé |
| `category` | ✅ Préservé |
| `edition` | ✅ Préservé |
| `collection` | ✅ Préservé |
| `monnaie` | ✅ Préservé |
| `format` | ✅ Préservé |

## Réinitialisation des ID

Les compteurs AUTO_INCREMENT sont réinitialisés à 1 pour :
- `livre`
- `lien_user_livre`
- `lien_auteur_livre`

## Workflow recommandé

### Option 1 : Purge DatabaseBook uniquement (livres)
Pour réimporter les livres sans toucher à DatabaseBookRef.

```bash
# Backup
mysqldump -u server -p DatabaseBook > backup_databasebook.sql

# Purge
mysql -u server -p DatabaseBook < sql/purge_livres.sql

# Resynchroniser depuis DatabaseBookRef existante
cd /home/jla23/project/DEV/Bdd-Books-DEV
php bin/console RecoverBDD_V3
```

### Option 2 : Purge DatabaseBookRef uniquement (base temporaire)
Pour forcer une nouvelle copie depuis Access.

```bash
# Purge
mysql -u server -p DatabaseBookRef < sql/purge_databasebookref.sql

# Resynchroniser depuis Access
cd /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3
java -jar RecoverBDD-Books.jar
```

### Option 3 : Purge COMPLÈTE (recommandé pour reset total)
Pour repartir complètement de zéro depuis Access.

```bash
# 1. Backup complet
mysqldump -u server -p --databases DatabaseBook DatabaseBookRef > backup_complet_$(date +%Y%m%d_%H%M%S).sql

# 2. Purge complète
mysql -u server -p < sql/purge_complete_system.sql

# 3. Resynchroniser depuis Access
cd /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3
java -jar RecoverBDD-Books.jar

# 4. Traiter la queue
cd /home/jla23/project/DEV/Bdd-Books-DEV
php bin/console RecoverBDD_V3
```

### Option 4 : Purge sécurisée avec backup automatique
```bash
# 1. Purge avec backup automatique
mysql -u server -p DatabaseBook < sql/purge_livres_safe.sql

# 2. Vérifier que tout est OK
mysql -u server -p DatabaseBook -e "SELECT COUNT(*) FROM livre; SELECT COUNT(*) FROM kiosk_num;"

# 3. Si OK, nettoyer les backups temporaires
mysql -u server -p DatabaseBook -e "DROP TABLE IF EXISTS livre_backup_temp, lien_user_livre_backup_temp, lien_auteur_livre_backup_temp;"

# 4. Resynchroniser
cd /home/jla23/project/DEV/Bdd-Books-DEV/RecoverBDD_Java_V3
java -jar RecoverBDD-Books.jar
cd ..
php bin/console RecoverBDD_V3
```

## Restauration (si nécessaire)

Si vous avez utilisé `purge_livres_safe.sql` et que vous voulez annuler :

```sql
-- Restaurer depuis les backups temporaires
INSERT INTO livre SELECT * FROM livre_backup_temp;
INSERT INTO lien_user_livre SELECT * FROM lien_user_livre_backup_temp;
INSERT INTO lien_auteur_livre SELECT * FROM lien_auteur_livre_backup_temp;

-- Nettoyer les backups
DROP TABLE livre_backup_temp, lien_user_livre_backup_temp, lien_auteur_livre_backup_temp;
```

Si vous avez utilisé `purge_livres.sql` :

```bash
# Restaurer depuis le dump
mysql -u server -p DatabaseBook < backup_avant_purge.sql
```

## Vérification après purge

```sql
-- Vérifier que les livres sont vides
SELECT COUNT(*) as livres FROM livre;
SELECT COUNT(*) as liens_user_livre FROM lien_user_livre;

-- Vérifier que les magazines sont intacts
SELECT COUNT(*) as magazines FROM kiosk_collec;
SELECT COUNT(*) as numeros FROM kiosk_num;
SELECT COUNT(*) as liens_user_magazine FROM lien_kiosk_num_user;

-- Vérifier les auto-increment
SHOW TABLE STATUS LIKE 'livre';
SHOW TABLE STATUS LIKE 'lien_user_livre';
```

## Cas d'usage

### Quand utiliser la purge ?

1. **Migration de données** : Avant de réimporter toutes les données depuis Access
2. **Nettoyage** : Base de test devenue trop volumineuse
3. **Corruption** : Données corrompues à réinitialiser
4. **Développement** : Reset de la base pour tests

### Quand NE PAS utiliser la purge ?

1. **Production** : Jamais sans backup complet et validation
2. **Données partielles** : Si vous voulez garder certains livres
3. **Incertitude** : Si vous n'êtes pas sûr de vouloir tout supprimer

## Support

En cas de problème, consulter :
- Les backups dans `/home/jla23/backups/`
- L'historique : `SELECT * FROM Historique ORDER BY date_action DESC LIMIT 100;`
- Les logs Symfony : `var/log/dev.log`
