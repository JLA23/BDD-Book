# Solutions de Scraping pour les Couvertures de Livres

## État actuel
Le service `BookCoverService.php` implémente déjà plusieurs sources :
- ✅ Google Books API
- ✅ Open Library API
- ✅ Scraping Amazon.fr (amélioré)

## Solutions implémentées

### 1. Amélioration du scraping Amazon
**Fichier**: `src/Service/BookCoverService.php`

**Améliorations apportées**:
- User-Agent moderne (Chrome 120)
- Headers HTTP complets pour éviter la détection
- Patterns regex multiples pour capturer différents formats d'images
- Nettoyage des URLs pour obtenir la meilleure qualité
- Validation des URLs d'images

**Méthodes disponibles**:
- `scrapeAmazonFr($isbn)` - Recherche via page de résultats
- `scrapeAmazonProductPage($isbn)` - Page produit directe (meilleure qualité)

### 2. Système de fallback intelligent
La méthode `findBestCover($isbn)` essaie les sources dans cet ordre :
1. **Google Books API** (gratuit, légal, bonne qualité)
2. **Open Library** (gratuit, légal)
3. **Amazon page produit** (scraping, meilleure qualité)
4. **Amazon recherche** (scraping, fallback)
5. **Open Library direct** (sans vérification)

## Solutions alternatives non implémentées

### Option A : Micro-service Node.js avec Puppeteer
**Avantages**: Contourne les protections anti-bot, JavaScript exécuté
**Inconvénients**: Infrastructure supplémentaire, coûts serveur

```javascript
// scraper-service.js
const puppeteer = require('puppeteer');
const express = require('express');

app.get('/scrape', async (req, res) => {
    const browser = await puppeteer.launch({ 
        headless: true,
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    await page.goto(`https://www.amazon.fr/s?k=${req.query.isbn}`);
    
    const imageUrl = await page.evaluate(() => {
        const img = document.querySelector('.s-image');
        return img ? img.src : null;
    });
    
    await browser.close();
    res.json({ imageUrl });
});
```

**Déploiement**: Docker container avec Node.js + Puppeteer

### Option B : Service externe ScraperAPI
**URL**: https://www.scraperapi.com/
**Coût**: 49$/mois pour 100k requêtes
**Avantages**: Gère les proxies, CAPTCHA, rotation IP

```php
public function scrapeWithScraperAPI(string $isbn): ?string
{
    $apiKey = 'YOUR_API_KEY';
    $targetUrl = urlencode("https://www.amazon.fr/s?k={$isbn}");
    $url = "http://api.scraperapi.com?api_key={$apiKey}&url={$targetUrl}";
    
    $html = file_get_contents($url);
    // Parser le HTML...
}
```

### Option C : Proxy rotatif avec cURL
**Avantages**: Plus difficile à bloquer
**Inconvénients**: Coût des proxies

```php
public function scrapeWithProxy(string $isbn): ?string
{
    $proxies = [
        'proxy1.example.com:8080',
        'proxy2.example.com:8080',
    ];
    
    $proxy = $proxies[array_rand($proxies)];
    
    $ch = curl_init("https://www.amazon.fr/s?k={$isbn}");
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0...');
    // ...
}
```

### Option D : API commerciales de couvertures
1. **Google Books API** (déjà implémenté) - GRATUIT
2. **Open Library** (déjà implémenté) - GRATUIT
3. **Goodreads API** - Fermée aux nouveaux utilisateurs
4. **ISBNdb.com** - 10$/mois pour 500 requêtes/jour

## Recommandations

### Court terme (Actuel)
✅ **Utiliser le système actuel amélioré**
- Google Books + Open Library couvrent ~70% des livres
- Amazon scraping pour les 30% restants
- Pas de coûts supplémentaires

### Moyen terme (Si besoin)
🔧 **Ajouter un cache Redis**
```php
// Cacher les URLs trouvées pour éviter de re-scraper
$redis->setex("cover:{$isbn}", 86400 * 30, $imageUrl);
```

### Long terme (Si volume important)
🚀 **Micro-service Puppeteer**
- Déployer sur un petit VPS (5€/mois)
- File d'attente pour les requêtes
- Rate limiting pour éviter les bans

## Utilisation

```php
// Dans votre contrôleur
$coverService = $this->get(BookCoverService::class);

// Recherche automatique avec fallback
$result = $coverService->findBestCover($isbn);
// Retourne: ['url' => 'https://...', 'source' => 'Google Books']

// Ou source spécifique
$url = $coverService->getCoverUrlFromGoogleBooks($isbn);
$url = $coverService->scrapeAmazonFr($isbn);
```

## Taux de succès estimés
- Google Books: ~60%
- Open Library: ~40%
- Amazon scraping: ~80% (mais peut être bloqué)
- **Combiné**: ~90-95%

## Notes légales
⚠️ Le scraping d'Amazon peut violer leurs CGU. Utiliser avec modération.
✅ Les APIs Google Books et Open Library sont légales et encouragées.
