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

// Route pour scraper Amazon - VERSION AMÉLIORÉE
app.get('/scrape/amazon', authenticate, async (req, res) => {
    const { isbn } = req.query;
    
    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    let browser;
    try {
        console.log(`\n[${new Date().toISOString()}] === DÉBUT SCRAPING ===`);
        console.log(`ISBN: ${isbn}`);
        
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
        
        // User-Agent
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Bloquer ressources inutiles
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
        console.log(`URL: ${searchUrl}`);
        
        await page.goto(searchUrl, { 
            waitUntil: 'domcontentloaded',
            timeout: 30000 
        });
        
        console.log('Page chargée, attente de 2 secondes...');
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Prendre un screenshot pour debug
        const screenshotPath = `/tmp/amazon-${isbn}.png`;
        await page.screenshot({ path: screenshotPath, fullPage: false });
        console.log(`Screenshot sauvegardé: ${screenshotPath}`);

        // Extraire toutes les images pour debug
        const allImages = await page.evaluate(() => {
            const images = [];
            document.querySelectorAll('img').forEach(img => {
                if (img.src) {
                    images.push({
                        src: img.src,
                        class: img.className,
                        alt: img.alt
                    });
                }
            });
            return images;
        });
        
        console.log(`Nombre total d'images trouvées: ${allImages.length}`);
        console.log('Premières images:', JSON.stringify(allImages.slice(0, 3), null, 2));

        // Extraire l'image du produit
        const imageUrl = await page.evaluate(() => {
            const selectors = [
                'img.s-image',
                'img[data-image-latency="s-product-image"]',
                '.s-product-image-container img',
                'img.s-image[src*="images-amazon"]',
                'div[data-component-type="s-search-result"] img',
                '.s-result-item img'
            ];

            console.log('Recherche avec sélecteurs...');
            
            for (const selector of selectors) {
                const imgs = document.querySelectorAll(selector);
                console.log(`Sélecteur "${selector}": ${imgs.length} trouvé(s)`);
                
                for (const img of imgs) {
                    if (img.src && (img.src.includes('images-amazon') || img.src.includes('media-amazon'))) {
                        console.log('Image trouvée:', img.src);
                        let url = img.src;
                        // Améliorer la qualité
                        url = url.replace(/\._[A-Z]+[0-9]+_\./, '.');
                        url = url.replace(/\._[A-Z]{2}[0-9]+,?[0-9]*_\./, '.');
                        return url;
                    }
                }
            }
            
            // Fallback: prendre la première image Amazon
            const allImgs = document.querySelectorAll('img');
            for (const img of allImgs) {
                if (img.src && (img.src.includes('images-amazon') || img.src.includes('media-amazon'))) {
                    if (!img.src.includes('transparent-pixel') && !img.src.includes('spinner')) {
                        console.log('Image fallback trouvée:', img.src);
                        return img.src;
                    }
                }
            }
            
            return null;
        });

        await browser.close();

        if (imageUrl) {
            console.log(`✅ SUCCESS: ${imageUrl}`);
            console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);
            res.json({ 
                success: true, 
                imageUrl,
                source: 'Amazon.fr',
                isbn,
                debug: {
                    totalImages: allImages.length,
                    screenshot: screenshotPath
                }
            });
        } else {
            console.log('❌ ÉCHEC: Aucune image trouvée');
            console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);
            res.json({ 
                success: false, 
                message: 'No image found',
                isbn,
                debug: {
                    totalImages: allImages.length,
                    screenshot: screenshotPath,
                    sampleImages: allImages.slice(0, 5)
                }
            });
        }

    } catch (error) {
        console.error(`❌ ERREUR: ${error.message}`);
        console.error(error.stack);
        console.log(`[${new Date().toISOString()}] === FIN SCRAPING (ERREUR) ===\n`);
        if (browser) await browser.close();
        res.status(500).json({ 
            success: false, 
            error: error.message,
            stack: error.stack
        });
    }
});

// Route pour scraper tous les sites en une fois
app.get('/scrape/all', authenticate, async (req, res) => {
    const { isbn } = req.query;
    
    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    console.log(`\n[${new Date().toISOString()}] === SCRAPING ALL SOURCES ===`);
    console.log(`ISBN: ${isbn}`);

    const results = [];
    
    // Fonction helper pour scraper un site
    async function scrapeSite(siteName, url, selectors) {
        let browser;
        try {
            console.log(`\n🔍 Scraping ${siteName}...`);
            
            browser = await puppeteer.launch({
                headless: 'new',
                args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
            });

            const page = await browser.newPage();
            await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
            await new Promise(resolve => setTimeout(resolve, 2000));

            const imageUrl = await page.evaluate((sels) => {
                for (const selector of sels) {
                    const img = document.querySelector(selector);
                    if (img && img.src && !img.src.includes('placeholder') && !img.src.includes('loading')) {
                        return img.src;
                    }
                }
                return null;
            }, selectors);

            await browser.close();

            if (imageUrl) {
                console.log(`✅ ${siteName}: ${imageUrl}`);
                return { url: imageUrl, source: siteName, quality: 'high' };
            } else {
                console.log(`❌ ${siteName}: Aucune image trouvée`);
            }
        } catch (error) {
            console.error(`❌ ${siteName} Error:`, error.message);
            if (browser) await browser.close();
        }
        return null;
    }

    // 1. BDGuest (nécessite de remplir le formulaire)
    let browser;
    try {
        console.log(`\n🔍 Scraping BDGuest...`);
        
        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Aller sur la page de recherche
        await page.goto('https://www.bedetheque.com/search', { waitUntil: 'domcontentloaded', timeout: 15000 });
        
        // Remplir le champ ISBN et soumettre le formulaire
        await page.evaluate((isbnValue) => {
            const input = document.querySelector('input[name="RechISBN"]');
            if (input) {
                input.value = isbnValue;
                // Trouver le formulaire parent et le soumettre
                const form = input.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }, isbn);
        
        // Attendre la navigation
        await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 });
        
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Chercher le lien vers la fiche album dans les résultats
        const albumLink = await page.evaluate(() => {
            // Chercher un lien qui pointe vers une page BD (contient "BD-" dans l'URL)
            const links = document.querySelectorAll('.search-list a, ul.search-list');
            for (const link of links) {
                if (link.href && link.href.includes('/BD-')) {
                    console.log('Lien trouvé:', link.href);
                    return link.href;
                }
            }
            return null;
        });

        if (!albumLink) {
            console.log(`❌ BDGuest: Aucun lien vers une fiche album trouvé`);
            await browser.close();
            return;
        }

        console.log(`📖 BDGuest: Navigation vers ${albumLink}`);
        
        await page.goto(albumLink, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Chercher l'image de couverture sur la page du livre
        const imageUrl = await page.evaluate(() => {
            const selectors = [
                '.bandeau-image.album img',
                '.bandeau-image img',
                'img[src*="Couvertures"]',
                'img.couv',
                'img[alt*="couverture"]',
                'a[href*="couverture"] img',
                '.image-tooltip img'
            ];
            
            console.log('Recherche image avec sélecteurs...');
            for (const selector of selectors) {
                const img = document.querySelector(selector);
                if (img) {
                    console.log(`Sélecteur "${selector}": trouvé`, img.src);
                    if (img.src && !img.src.includes('placeholder') && !img.src.includes('loading')) {
                        return img.src;
                    }
                }
            }
            return null;
        });

        await browser.close();

        if (imageUrl) {
            console.log(`✅ BDGuest: ${imageUrl}`);
            results.push({ url: imageUrl, source: 'BDGuest', quality: 'high' });
        } else {
            console.log(`❌ BDGuest: Aucune image trouvée`);
        }
    } catch (error) {
        console.error(`❌ BDGuest Error:`, error.message);
        if (browser) await browser.close();
    }

    // 2. Fnac
    const fnacResult = await scrapeSite(
        'Fnac',
        `https://www.fnac.com/SearchResult/ResultList.aspx?Search=${isbn}`,
        ['img[data-src*="multimedia"]', 'img[src*="multimedia"]', '.Article-itemVisual img']
    );
    if (fnacResult) results.push(fnacResult);

    // 3. Decitre
    const decitreResult = await scrapeSite(
        'Decitre',
        `https://www.decitre.fr/rechercher/result?q=${isbn}`,
        ['img[data-src*="products"]', 'img[src*="products"]', '.product-image img']
    );
    if (decitreResult) results.push(decitreResult);

    // 4. Amazon
    const amazonResult = await scrapeSite(
        'Amazon',
        `https://www.amazon.fr/s?k=${isbn}`,
        ['img.s-image', 'img[data-image-latency="s-product-image"]', '.s-product-image-container img']
    );
    if (amazonResult) results.push(amazonResult);

    console.log(`\n📊 Total: ${results.length} image(s) trouvée(s)`);
    console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);

    res.json({
        success: true,
        isbn,
        images: results,
        count: results.length
    });
});

// Démarrer le serveur
app.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Scraper service running on port ${PORT}`);
    console.log(`📝 API Key: ${API_KEY.substring(0, 8)}...`);
    console.log(`🔍 Test: curl "http://localhost:${PORT}/health"`);
});
