# Commande de Scraping Automatique des Couvertures

## Description

La commande `app:scrape-covers` permet de scraper et télécharger automatiquement les images de couverture pour tous les livres d'un utilisateur qui ont un ISBN.

## Fonctionnalités

- ✅ Scraping via Puppeteer sur 4 sources : **BDGuest**, **Fnac**, **Decitre**, **Amazon**
- ✅ Priorité automatique : **Amazon** en premier, puis les autres sources
- ✅ Téléchargement des images sur le serveur (dans `/public/uploads/covers/`)
- ✅ Stockage du nom de fichier dans le champ `image_2` de la base de données
- ✅ Pauses configurables entre chaque scraping pour éviter les blocages
- ✅ Suppression automatique de l'ancienne image lors du remplacement
- ✅ Barre de progression et logs détaillés

## Utilisation

### Syntaxe de base

```bash
php bin/console app:scrape-covers <user-id>
```

### Options disponibles

| Option | Raccourci | Description | Valeur par défaut |
|--------|-----------|-------------|-------------------|
| `--delay` | `-d` | Délai en secondes entre chaque scraping | 3 secondes |
| `--force` | `-f` | Forcer le scraping même si une image existe déjà | Non activé |
| `--limit` | `-l` | Nombre maximum de livres à traiter | Aucune limite |

### Exemples

#### 1. Scraper tous les livres d'un utilisateur (ID: 1)
```bash
php bin/console app:scrape-covers 1
```

#### 2. Scraper avec un délai de 5 secondes entre chaque livre
```bash
php bin/console app:scrape-covers 1 --delay=5
```

#### 3. Forcer le scraping même pour les livres qui ont déjà une image
```bash
php bin/console app:scrape-covers 1 --force
```

#### 4. Traiter seulement les 10 premiers livres
```bash
php bin/console app:scrape-covers 1 --limit=10
```

#### 5. Combinaison d'options
```bash
php bin/console app:scrape-covers 1 --delay=5 --limit=20 --force
```

## Comportement

1. **Sélection des livres** : La commande sélectionne tous les livres de l'utilisateur qui ont un ISBN
2. **Filtrage** : Par défaut, seuls les livres sans image (`image_2` vide) sont traités (sauf avec `--force`)
3. **Scraping** : Pour chaque livre, appel du service Puppeteer qui scrape les 4 sources
4. **Priorité** : Si une image Amazon est trouvée, elle est utilisée en priorité, sinon la première image disponible
5. **Téléchargement** : L'image est téléchargée et sauvegardée dans `/public/uploads/covers/`
6. **Nommage** : Format du fichier : `{livre_id}_{uniqid}.{extension}`
7. **Base de données** : Le champ `image_2` est mis à jour avec le nom du fichier
8. **Pause** : Attente du délai configuré avant de passer au livre suivant

## Sortie de la commande

La commande affiche :
- Le nombre total de livres à traiter
- Une barre de progression
- Pour chaque livre :
  - Titre et ISBN
  - Source de l'image sélectionnée
  - URL de l'image
  - Nom du fichier sauvegardé
  - Statut (succès ou erreur)
- Résumé final avec le nombre de succès et d'échecs

## Exemple de sortie

```
Scraping des couvertures pour l'utilisateur: john_doe
=====================================

Nombre de livres à traiter: 25

Livre 1/25: Batman - Death Metal (ISBN: 9791026821854)
------------------------------------------------------
Image sélectionnée: Amazon - https://m.media-amazon.com/images/I/71axxX4bDnL._AC_UL320_.jpg
Image sauvegardée: 42_63f8a9b2c1d4e.jpg
✓ Succès

Pause de 3 secondes...

[...]

Scraping terminé!
Total: 25
Succès: 23
Échecs: 2
```

## Prérequis

- Le service Puppeteer doit être démarré : `pm2 status scraper-service`
- Le répertoire `/public/uploads/covers/` doit être accessible en écriture
- Les variables d'environnement `PUPPETEER_SERVICE_URL` et `PUPPETEER_API_KEY` doivent être configurées

## Gestion des erreurs

La commande gère automatiquement les erreurs suivantes :
- Utilisateur non trouvé
- Aucune image trouvée pour un livre
- Erreur de téléchargement
- Erreur de sauvegarde du fichier
- Erreur du service Puppeteer

En cas d'erreur sur un livre, la commande continue avec le suivant.

## Notes importantes

⚠️ **Délai recommandé** : Utilisez un délai d'au moins 3 secondes pour éviter d'être bloqué par les sites web

⚠️ **Temps d'exécution** : Le scraping peut prendre jusqu'à 2 minutes par livre (4 sites à scraper)

⚠️ **Espace disque** : Assurez-vous d'avoir suffisamment d'espace pour stocker les images

## Intégration avec l'interface web

Les images téléchargées sont automatiquement affichées dans l'interface web grâce à la méthode `getBestImage()` de l'entité `Livre` qui priorise :
1. `image_2` (fichier téléchargé) ✅
2. `imageUrl` (URL externe)
3. `image` (blob en base de données)
