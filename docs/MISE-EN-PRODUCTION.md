# Procédure de mise en production (MEP)

Document généré pour le déploiement des modifications de la branche `feature/section_DVD_Musique` (état non commité au moment de la rédaction).

**Périmètre :** DVD, musique, jeux, briques, magazines, statistiques, stockage images local (`stored_path`), barres de recherche / scanner, UI propriétaires, etc.

---

## 1. Avant la MEP

### 1.1 Sauvegardes

```bash
# Base de données (adapter user/host/base)
mysqldump -u USER -p NOM_BASE > backup_bdd_$(date +%Y%m%d_%H%M).sql

# Dossier uploads existant (couvertures, DVD, etc.)
tar -czf backup_uploads_$(date +%Y%m%d).tar.gz public/uploads/
```

### 1.2 Commit / livraison du code

Les changements doivent être versionnés avant déploiement sur le serveur :

```bash
cd /chemin/vers/Bdd-Books-DEV
git status
git add …   # ou git add -A selon votre politique
git commit -m "Message décrivant le lot de modifications"
git push origin feature/section_DVD_Musique
```

Merger ensuite vers la branche déployée en production (`main` / `master`) via pull request, ou déployer la branche validée selon votre processus.

### 1.3 Fenêtre & mode maintenance (recommandé)

```bash
php bin/console cache:clear
# Optionnel : activer une page de maintenance côté serveur web
# touch public/maintenance.html
```

---

## 2. Déploiement applicatif

Sur le **serveur de production** (répertoire du projet) :

```bash
cd /chemin/vers/Bdd-Books-DEV

# Code
git fetch origin
git checkout BRANCHE_CIBLE
git pull origin BRANCHE_CIBLE

# Dépendances PHP
composer install --no-dev --optimize-autoloader

# Assets front (SCSS modifié : assets/scss/styles/_index.scss, public/css/app.css, bdd-search-bar.css)
npm ci
npm run build

# Migrations Doctrine (voir section 3)
php bin/console doctrine:migrations:migrate --no-interaction

# Cache Symfony
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

---

## 3. Migrations base de données

À appliquer **dans l’ordre chronologique** si la production ne les a pas encore :

| Version | Description |
|---------|-------------|
| `DoctrineMigrations\Version20260501103000` | Crée `game_console_alias` (mapping libellé → console) |
| `DoctrineMigrations\Version20260502110000` | Ajoute `game_console.igdb_platform_id` |
| `DoctrineMigrations\Version20260503143000` | Supprime colonnes string sur `lien_user_game` (remplacées par FK) |
| `DoctrineMigrations\Version20260504120000` | Ajoute `dvd.ean` |
| `DoctrineMigrations\Version20260505120000` | Ajoute `dvd.edition` |
| `DoctrineMigrations\Version20260506120000` | Corrige `game_console.igdb_platform_id` (IGDB) |
| `DoctrineMigrations\Version20260517120000` | Ajoute `brick_set.ean` |
| `DoctrineMigrations\Version20260517180000` | Ajoute `prix_achat` et `date_achat` sur `lien_kiosk_num_user` |
| `DoctrineMigrations\Version20260518120000` | Colonne `stored_path` (images locales, URL source conservée) |

Tables concernées par `stored_path` : `livre`, `dvd`, `musique`, `game`, `kiosk_collec`, `kiosk_num`, `dvd_user_collection`, `musique_user_collection`, `lien_user_game`.

### Vérification

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:migrations:list
```

---

## 4. Fichiers & droits disque

### 4.1 Dossiers `public/uploads/` (gitignorés)

Créer et rendre inscriptibles les répertoires (adapter l’utilisateur du serveur web, ex. `www-data`) :

```bash
mkdir -p public/uploads/books
mkdir -p public/uploads/magazines/collections
mkdir -p public/uploads/magazines/numeros
mkdir -p public/uploads/dvd
mkdir -p public/uploads/dvd/user
mkdir -p public/uploads/musique
mkdir -p public/uploads/musique/user
mkdir -p public/uploads/games
mkdir -p public/uploads/game
mkdir -p public/uploads/game/user
mkdir -p public/uploads/brick
mkdir -p public/uploads/avatars
mkdir -p public/uploads/covers

chown -R www-data:www-data public/uploads
chmod -R 775 public/uploads
```

### 4.2 Nouveautés à déployer avec le code

- `public/css/bdd-search-bar.css`
- `public/js/bdd-barcode-scanner.js`
- `src/Service/Media/` (`ImageStorageService`, `MediaImageSyncService`, `ImageMediaType`)
- `src/Command/FetchStoredImagesCommand.php` (`app:media:fetch-stored-images`)
- `src/Entity/Trait/StoredImagePathTrait.php`
- `src/Form/Type/MonthType.php` (dates mois/année magazines)
- `templates/components/` (recherche, propriétaires, etc.)
- `templates/magazines/_numero_owners_fields.html.twig`, `edit_owner.html.twig`, `user_collection.html.twig`

Configuration Symfony : `config/services.yaml` (injection `ImageStorageService` avec `%kernel.project_dir%`).

---

## 5. Post-déploiement

### 5.1 Images locales (`stored_path`)

Les **nouvelles** fiches enregistrent une copie locale à l’insertion/édition. Pour les données **déjà en base** :

```bash
# Simulation
php bin/console app:media:fetch-stored-images --dry-run

# Par type (recommandé pour limiter la charge)
php bin/console app:media:fetch-stored-images --type=magazines
php bin/console app:media:fetch-stored-images --type=dvd
php bin/console app:media:fetch-stored-images --type=musique
php bin/console app:media:fetch-stored-images --type=games
php bin/console app:media:fetch-stored-images --type=books
php bin/console app:media:fetch-stored-images --type=brick

# Tout d’un coup (peut être long)
php bin/console app:media:fetch-stored-images --type=all
```

Options :

- `--limit=100` : limite par type
- `--force` : réécrit les fichiers même si `stored_path` existe déjà

### 5.2 Jeux — entités / consoles (si base déjà en prod)

```bash
php bin/console app:migrate-game-entities
php bin/console app:sync-game-console-igdb-ids
```

À lancer seulement si ces commandes n’ont pas déjà été exécutées sur l’environnement cible.

### 5.3 Finalisation

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
# Désactiver la page de maintenance si activée
```

---

## 6. Plan de tests (smoke test)

| Zone | Action | Contrôle |
|------|--------|----------|
| Accueil | `/` | Mise en page centrée, liens sections |
| Statistiques | `/statistiques` | Compteurs DVD + musique, liens collections |
| DVD | Liste, fiche, création (API), collection | Jaquette, propriétaires, prix d’achat |
| Musique | Idem | Pochette, collection |
| Jeux | Idem | Jaquette, galerie |
| Briques | Accueil, recherche EAN, fiche | Scanner / barre de recherche |
| Magazines | Fiche, `/numeros/nouveau` | Bloc « Ma propriété », date `type="month"`, image |
| Livres | Fiche, changement couverture | Affichage (`stored_path` ou legacy) |
| Upload | Ajouter/modifier une image | Fichier présent sous `public/uploads/…` |

---

## 7. Rollback

1. Restaurer la base : `mysql -u USER -p NOM_BASE < backup_bdd_YYYYMMDD_HHMM.sql`
2. Revenir au commit précédent :
   ```bash
   git checkout TAG_OU_COMMIT
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php bin/console cache:clear --env=prod
   ```
3. Les fichiers ajoutés dans `public/uploads/` peuvent rester (sans impact si le code est rollback).

**Attention :** la migration `Version20260503143000` supprime des colonnes sur `lien_user_game` — rollback BDD indispensable en cas de retour arrière sur cette version.

---

## 8. Synthèse fonctionnelle du lot

- **DVD / musique / jeux / briques** : sections complètes (EAN, API, collections utilisateur, prix d’achat propriétaire).
- **Magazines** : propriétaires avec prix/date, formulaire multi-numéros, sélecteur mois/année, images fichier + `stored_path`.
- **Images** : copie sur disque par type (`public/uploads/…`) + conservation des URL ; commande de rattrapage `app:media:fetch-stored-images`.
- **UI** : barres de recherche unifiées, scanner code-barres mobile, carte propriétaires repliable, centrage pages d’accueil.
- **Statistiques** : intégration DVD et musique, liens vers collections par utilisateur.

---

## 9. Points d’attention

1. **Commit obligatoire** avant MEP : migrations et nouveaux fichiers doivent être sur le dépôt distant.
2. **`npm run build`** : requis si `public/build/` n’est pas versionné (Webpack Encore).
3. **Commande images** : peut être longue et solliciter le réseau ; prévoir exécution hors pic ou par `--type`.
4. **Variables d’environnement** (`.env.local` en prod) : vérifier selon usage :
   - `DVDFR_API_KEY`
   - `TWITCH_CLIENT_ID`, `TWITCH_CLIENT_SECRET` (IGDB / jeux)
   - `REBRICKABLE_API_KEY`, `BRICKSET_API_KEY` (briques)
   - `PUPPETEER_SERVICE_URL`, `PUPPETEER_API_KEY` (couvertures livres, si utilisé)
5. **Droits `public/uploads/`** : écriture obligatoire pour le stockage d’images.
6. **Migration jeux `20260503143000`** : irréversible sans sauvegarde BDD.

---

## 10. Commandes utiles (référence rapide)

```bash
# État git
git status
git log -5 --oneline

# Migrations
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction

# Cache prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Images existantes
php bin/console app:media:fetch-stored-images --dry-run
php bin/console app:media:fetch-stored-images --type=all

# Couvertures livres (existant)
php bin/console app:scrape-covers USER_ID --limit=10
```
