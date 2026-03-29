# Configuration et Gestion de la Base de Données

Ce document explique comment configurer et gérer les bases de données pour éviter d'impacter la production.

## 📋 Structure des fichiers

```
.env.example          # Template de configuration (à copier en .env)
.env                  # Configuration locale (ignoré par git)
.env.local            # Surcharge locale (prioritaire, ignoré par git)
scripts/
  ├── clone_database.sh   # Script de clonage de base de données
  └── db_commands.sh      # Commandes utilitaires
var/backups/          # Sauvegardes locales (ignoré par git)
```

## 🚀 Configuration initiale

### 1. Créer votre fichier de configuration

```bash
# Copier le template
cp .env.example .env

# Ou créer un fichier de surcharge locale
cp .env.example .env.local
```

### 2. Modifier les paramètres de base de données

Éditez `.env` ou `.env.local` et modifiez `DATABASE_URL` :

```env
# Format: mysql://USER:PASSWORD@HOST:PORT/DATABASE_NAME?serverVersion=VERSION

# Exemple pour développement local
DATABASE_URL="mysql://root:password@127.0.0.1:3306/bdd_books_dev?serverVersion=8.0"

# Exemple pour MariaDB
DATABASE_URL="mysql://dev_user:dev_pass@localhost:3306/bdd_books_dev?serverVersion=mariadb-10.5"
```

### 3. Vider le cache après modification

```bash
php bin/console cache:clear
```

## 🔄 Cloner la base de production

### Méthode interactive (recommandée)

```bash
./scripts/db_commands.sh clone
```

Le script vous guidera pour :
- Entrer les paramètres de la base source (production)
- Entrer les paramètres de la base cible (développement)
- Optionnellement anonymiser les données sensibles

### Méthode directe

```bash
./scripts/clone_database.sh \
    --source-host localhost \
    --source-db bdd_books_prod \
    --source-user prod_user \
    --source-pass "mot_de_passe_prod" \
    --target-host localhost \
    --target-db bdd_books_dev \
    --target-user dev_user \
    --target-pass "mot_de_passe_dev" \
    --anonymize
```

### Options disponibles

| Option | Description |
|--------|-------------|
| `--source-host` | Hôte de la base source (défaut: localhost) |
| `--source-db` | Nom de la base source (obligatoire) |
| `--source-user` | Utilisateur source (défaut: root) |
| `--source-pass` | Mot de passe source |
| `--target-host` | Hôte de la base cible (défaut: localhost) |
| `--target-db` | Nom de la base cible (obligatoire) |
| `--target-user` | Utilisateur cible (défaut: root) |
| `--target-pass` | Mot de passe cible |
| `--anonymize` | Anonymiser les données sensibles (emails, noms, mots de passe) |
| `--no-data` | Copier uniquement la structure (pas les données) |

## 💾 Sauvegardes

### Créer une sauvegarde

```bash
./scripts/db_commands.sh backup
```

Les sauvegardes sont stockées dans `var/backups/` au format `.sql.gz`.

### Restaurer une sauvegarde

```bash
./scripts/db_commands.sh restore
```

Le script affiche les sauvegardes disponibles et vous permet de choisir laquelle restaurer.

## 📊 Vérifier la configuration actuelle

```bash
./scripts/db_commands.sh status
```

Affiche :
- Le fichier de configuration utilisé
- Les paramètres de connexion
- Le nombre de tables dans la base

## ⚠️ Bonnes pratiques

### Sécurité

1. **Ne jamais commiter `.env` ou `.env.local`** - ils contiennent des mots de passe
2. **Utiliser des mots de passe différents** entre dev et prod
3. **Toujours anonymiser** les données lors du clonage vers dev

### Workflow recommandé

1. **Cloner la base de prod** avec anonymisation :
   ```bash
   ./scripts/db_commands.sh clone
   # Répondre "o" à la question d'anonymisation
   ```

2. **Mettre à jour `.env.local`** avec les paramètres de la base clonée

3. **Vider le cache** :
   ```bash
   php bin/console cache:clear
   ```

4. **Vérifier la connexion** :
   ```bash
   ./scripts/db_commands.sh status
   ```

5. **Avant chaque modification importante**, faire une sauvegarde :
   ```bash
   ./scripts/db_commands.sh backup
   ```

## 🔧 Dépannage

### Erreur de connexion

```
Connexion échouée - vérifiez les identifiants
```

- Vérifiez que MySQL/MariaDB est démarré
- Vérifiez les identifiants dans `.env` ou `.env.local`
- Vérifiez que l'utilisateur a les droits sur la base

### Base de données non trouvée

```bash
# Créer la base manuellement
mysql -u root -p -e "CREATE DATABASE bdd_books_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Permissions insuffisantes

```bash
# Donner tous les droits à l'utilisateur sur la base de dev
mysql -u root -p -e "GRANT ALL PRIVILEGES ON bdd_books_dev.* TO 'dev_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"
```

## 📝 Commandes utiles Symfony

```bash
# Vérifier la connexion à la base
php bin/console doctrine:database:create --if-not-exists

# Mettre à jour le schéma
php bin/console doctrine:schema:update --dump-sql  # Voir les changements
php bin/console doctrine:schema:update --force     # Appliquer

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```
