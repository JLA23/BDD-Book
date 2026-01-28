# Guide : Déployer un serveur Puppeteer sur Ubuntu

## Prérequis
- Serveur Ubuntu 20.04 ou 22.04
- Accès SSH root ou sudo
- Port 3000 ouvert (ou autre port de ton choix)

## Étape 1 : Installation de Node.js

```bash
# Se connecter au serveur
ssh user@ton-serveur.com

# Mettre à jour le système
sudo apt update && sudo apt upgrade -y

# Installer Node.js 18.x (LTS)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Vérifier l'installation
node --version  # Devrait afficher v18.x.x
npm --version   # Devrait afficher 9.x.x
```

## Étape 2 : Installation des dépendances Puppeteer

```bash
# Installer les dépendances système pour Chromium
# Version pour Ubuntu 24.04+ (avec les nouveaux noms de paquets)
sudo apt install -y \
    libasound2t64 \
    libatk1.0-0t64 \
    libc6 \
    libcairo2 \
    libcups2t64 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgcc-s1 \
    libgdk-pixbuf2.0-0 \
    libglib2.0-0t64 \
    libgtk-3-0t64 \
    libnspr4 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libnss3 \
    lsb-release \
    xdg-utils \
    wget

# Si tu es sur Ubuntu 20.04 ou 22.04, utilise plutôt cette commande :
# sudo apt install -y libasound2 libatk1.0-0 libcups2 libgcc1 libgconf-2-4 \
#   libglib2.0-0 libgtk-3-0 libappindicator1 gconf-service \
#   (+ les autres paquets ci-dessus)
```

## Étape 3 : Créer le projet

```bash
# Créer un dossier pour le projet
mkdir -p /var/www/scraper-service
cd /var/www/scraper-service

# Initialiser le projet Node.js
npm init -y

# Installer les dépendances
npm install express puppeteer cors dotenv
```

## Étape 4 : Créer le serveur

Créer le fichier `server.js` :

```bash
nano server.js
```

Coller ce code :

```javascript
const express = require('express');
const puppeteer = require('puppeteer');
const cors = require('cors');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;
const API_KEY = process.env.API_KEY || 'votre-cle-secrete-ici';

// Middleware
app.use(cors());
app.use(express.json());

// Middleware d'authentification
const authenticate = (req, res, next) => {
    const apiKey = req.headers['x-api-key'] || req.query.api_key;
    if (apiKey !== API_KEY) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
};

// Route de santé
app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Route pour scraper Amazon
app.get('/scrape/amazon', authenticate, async (req, res) => {
    const { isbn } = req.query;
    
    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    let browser;
    try {
        console.log(`[${new Date().toISOString()}] Scraping Amazon for ISBN: ${isbn}`);
        
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--window-size=1920x1080'
            ]
        });

        const page = await browser.newPage();
        
        // Configurer le User-Agent
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Bloquer les ressources inutiles pour accélérer
        await page.setRequestInterception(true);
        page.on('request', (request) => {
            const resourceType = request.resourceType();
            if (['stylesheet', 'font', 'media'].includes(resourceType)) {
                request.abort();
            } else {
                request.continue();
            }
        });

        // Aller sur Amazon
        const searchUrl = `https://www.amazon.fr/s?k=${isbn}`;
        await page.goto(searchUrl, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });

        // Extraire l'image
        const imageUrl = await page.evaluate(() => {
            // Essayer plusieurs sélecteurs
            const selectors = [
                '.s-image',
                'img[data-image-latency="s-product-image"]',
                '.s-product-image-container img',
                'img.s-image[src*="images-amazon"]'
            ];

            for (const selector of selectors) {
                const img = document.querySelector(selector);
                if (img && img.src && img.src.includes('images-amazon')) {
                    // Améliorer la qualité en retirant les dimensions
                    let url = img.src;
                    url = url.replace(/\._[A-Z]+[0-9]+_\./, '.');
                    url = url.replace(/\._[A-Z]{2}[0-9]+,?[0-9]*_\./, '.');
                    return url;
                }
            }
            return null;
        });

        await browser.close();

        if (imageUrl) {
            console.log(`[${new Date().toISOString()}] Success: ${imageUrl}`);
            res.json({ 
                success: true, 
                imageUrl,
                source: 'Amazon.fr',
                isbn 
            });
        } else {
            console.log(`[${new Date().toISOString()}] No image found for ISBN: ${isbn}`);
            res.json({ 
                success: false, 
                message: 'No image found',
                isbn 
            });
        }

    } catch (error) {
        console.error(`[${new Date().toISOString()}] Error:`, error.message);
        if (browser) await browser.close();
        res.status(500).json({ 
            success: false, 
            error: error.message 
        });
    }
});

// Route pour scraper une URL personnalisée
app.post('/scrape/custom', authenticate, async (req, res) => {
    const { url, selector } = req.body;
    
    if (!url) {
        return res.status(400).json({ error: 'URL is required' });
    }

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });

        const page = await browser.newPage();
        await page.goto(url, { waitUntil: 'networkidle2' });

        const imageUrl = await page.evaluate((sel) => {
            const img = document.querySelector(sel || 'img');
            return img ? img.src : null;
        }, selector);

        await browser.close();

        res.json({ success: true, imageUrl, url });

    } catch (error) {
        if (browser) await browser.close();
        res.status(500).json({ success: false, error: error.message });
    }
});

// Démarrer le serveur
app.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Scraper service running on port ${PORT}`);
    console.log(`📝 API Key: ${API_KEY.substring(0, 8)}...`);
});
```

## Étape 5 : Configuration

Créer le fichier `.env` :

```bash
nano .env
```

Ajouter :

```env
PORT=3000
API_KEY=votre-cle-secrete-super-longue-et-complexe
```

## Étape 6 : Installer PM2 (gestionnaire de processus)

```bash
# Installer PM2 globalement
sudo npm install -g pm2

# Démarrer le serveur avec PM2
pm2 start server.js --name scraper-service

# Configurer PM2 pour démarrer au boot
pm2 startup
pm2 save

# Vérifier le statut
pm2 status
pm2 logs scraper-service
```

## Étape 7 : Configurer le pare-feu

```bash
# Autoriser le port 3000
sudo ufw allow 3000/tcp

# Ou si tu utilises Nginx en reverse proxy (recommandé)
sudo ufw allow 'Nginx Full'
```

## Étape 8 : (Optionnel) Configurer Nginx en reverse proxy

```bash
# Installer Nginx
sudo apt install -y nginx

# Créer la configuration
sudo nano /etc/nginx/sites-available/scraper
```

Ajouter :

```nginx
server {
    listen 80;
    server_name scraper.ton-domaine.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        
        # Timeout pour les requêtes longues
        proxy_read_timeout 60s;
        proxy_connect_timeout 60s;
    }
}
```

Activer :

```bash
sudo ln -s /etc/nginx/sites-available/scraper /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Étape 9 : (Optionnel) SSL avec Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d scraper.ton-domaine.com
```

## Utilisation depuis PHP

Dans ton `BookCoverService.php` :

```php
public function scrapeWithPuppeteerService(?string $isbn): ?string
{
    $isbn = $this->cleanIsbn($isbn);
    if (empty($isbn)) {
        return null;
    }

    try {
        $apiKey = $_ENV['PUPPETEER_API_KEY'] ?? 'votre-cle-secrete';
        $serviceUrl = $_ENV['PUPPETEER_SERVICE_URL'] ?? 'http://votre-serveur.com:3000';
        
        $url = "{$serviceUrl}/scrape/amazon?isbn={$isbn}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'header' => "X-API-Key: {$apiKey}\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data['success'] && !empty($data['imageUrl'])) {
                return $data['imageUrl'];
            }
        }
    } catch (\Exception $e) {
        // Log error
    }
    
    return null;
}
```

Ajouter dans ton `.env` Symfony :

```env
PUPPETEER_SERVICE_URL=http://votre-serveur.com:3000
PUPPETEER_API_KEY=votre-cle-secrete-super-longue-et-complexe
```

## Commandes utiles PM2

```bash
# Voir les logs en temps réel
pm2 logs scraper-service

# Redémarrer le service
pm2 restart scraper-service

# Arrêter le service
pm2 stop scraper-service

# Voir les statistiques
pm2 monit

# Voir les infos
pm2 info scraper-service
```

## Tests

```bash
# Test de santé
curl http://localhost:3000/health

# Test de scraping (remplace YOUR_API_KEY)
curl "http://localhost:3000/scrape/amazon?isbn=9782253006329&api_key=YOUR_API_KEY"
```

## Optimisations

### 1. Limiter le nombre de requêtes simultanées

Modifier `server.js` pour ajouter :

```javascript
const rateLimit = require('express-rate-limit');

const limiter = rateLimit({
    windowMs: 60 * 1000, // 1 minute
    max: 10 // 10 requêtes par minute
});

app.use('/scrape', limiter);
```

### 2. Ajouter un cache

```bash
npm install node-cache
```

```javascript
const NodeCache = require('node-cache');
const cache = new NodeCache({ stdTTL: 86400 }); // 24h

// Dans la route
const cached = cache.get(isbn);
if (cached) {
    return res.json(cached);
}
// ... après scraping
cache.set(isbn, result);
```

## Coûts estimés

- **VPS basique** (1 CPU, 1GB RAM) : 5-10€/mois
  - Vultr, DigitalOcean, Hetzner, OVH
- **Bande passante** : Généralement illimitée
- **Total** : ~5-10€/mois

## Sécurité

1. ✅ Utiliser une clé API forte
2. ✅ Limiter les requêtes (rate limiting)
3. ✅ Utiliser HTTPS (Let's Encrypt)
4. ✅ Mettre à jour régulièrement : `npm update`
5. ✅ Surveiller les logs : `pm2 logs`

## Monitoring

```bash
# Installer PM2 Plus (monitoring gratuit)
pm2 link <secret> <public>
```

Tableau de bord : https://app.pm2.io/
