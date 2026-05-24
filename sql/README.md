# Scripts SQL — mise à jour base de données

Les évolutions de schéma sont gérées par des **scripts SQL manuels** dans ce dossier (plus de migrations Doctrine PHP).

**Avant chaque exécution :** sauvegarde complète de la base (`mysqldump`).

**Convention :** les scripts sont ré-exécutables autant que possible (`CREATE TABLE IF NOT EXISTS`, `INSERT … ON DUPLICATE KEY UPDATE`). Les erreurs du type *duplicate column*, *already exists* ou *duplicate key* indiquent en général que l’étape est déjà faite — passer à la suite.

## Ordre recommandé en production

| Ordre | Fichier | Rôle |
|-------|---------|------|
| 1 | `prod_dvd_musique.sql` | Crée `dvd`, `musique`, `dvd_user_collection`, `musique_user_collection` |
| 2 | `prod_jeux_etapes_simple.sql` | Références jeux (consoles, stores, FK `lien_user_game`) |
| 3 | `drop_lien_user_game_legacy_columns.sql` | Supprime les colonnes texte sur `lien_user_game` (après migration FK) |
| 4 | `prod_complement.sql` | `stored_path`, magazines propriétaires, `brick_set.ean`, correction IGDB |

Référence complète jeux (alias IGDB, etc.) : `game_entities_migration.sql`.

## Exécution

```bash
mysql -u USER -p NOM_BASE < sql/prod_dvd_musique.sql
mysql -u USER -p NOM_BASE < sql/prod_jeux_etapes_simple.sql
# … selon l’état de la base
```

Ou depuis le client MySQL :

```sql
SOURCE /chemin/vers/projet/sql/prod_dvd_musique.sql;
```

## Vérification après déploiement

```bash
php bin/console doctrine:schema:update --dump-sql --complete
```

La sortie doit être vide (ou ne contenir que des écarts volontaires). **Ne pas** lancer `doctrine:schema:update --force` en production sans relire le diff.

## Commandes Symfony complémentaires (hors SQL)

```bash
php bin/console app:seed-console-slug-aliases --env=prod   # alias consoles jeux
php bin/console app:media:fetch-stored-images --dry-run      # rattrapage images
```

Voir aussi `docs/MISE-EN-PRODUCTION.md`.
