const express = require('express');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const cors = require('cors');
require('dotenv').config();

// Activer le plugin stealth pour contourner les anti-bots
puppeteer.use(StealthPlugin());

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

        // Étape 1 : Trouver et cliquer sur le premier résultat produit
        const productLink = await page.evaluate(() => {
            const selectors = [
                'div[data-component-type="s-search-result"] a.a-link-normal.s-no-outline',
                'div[data-component-type="s-search-result"] .a-link-normal[href*="/dp/"]',
                'div[data-component-type="s-search-result"] h2 a',
                '.s-result-item a[href*="/dp/"]'
            ];
            for (const selector of selectors) {
                const link = document.querySelector(selector);
                if (link && link.href) {
                    return link.href;
                }
            }
            return null;
        });

        if (!productLink) {
            console.log('❌ Aucun résultat trouvé sur Amazon');
            await browser.close();
            return res.json({ success: false, message: 'No product found on Amazon', isbn, debug: { screenshot: screenshotPath } });
        }

        console.log(`📖 Produit trouvé, navigation vers: ${productLink}`);
        await page.goto(productLink, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Screenshot page produit
        const screenshotProduct = `/tmp/amazon-product-${isbn}.png`;
        await page.screenshot({ path: screenshotProduct, fullPage: false });
        console.log(`Screenshot produit: ${screenshotProduct}`);

        // Étape 2 : Cliquer sur l'image principale du produit pour ouvrir le viewer
        const mainImageClicked = await page.evaluate(() => {
            const selectors = [
                '#imgBlkFront',
                '#landingImage',
                '#ebooksImgBlkFront',
                '#main-image',
                '#imgTagWrapperId img',
                '#imageBlock img'
            ];
            for (const selector of selectors) {
                const img = document.querySelector(selector);
                if (img) {
                    img.click();
                    return true;
                }
            }
            return false;
        });

        if (mainImageClicked) {
            console.log('🖱️ Image principale cliquée, attente du viewer...');
            await new Promise(resolve => setTimeout(resolve, 3000));

            // Screenshot après clic
            const screenshotViewer = `/tmp/amazon-viewer-${isbn}.png`;
            await page.screenshot({ path: screenshotViewer, fullPage: false });
            console.log(`Screenshot viewer: ${screenshotViewer}`);
        } else {
            console.log('⚠️ Image principale non trouvée pour clic');
        }

        // Étape 3 : Chercher l'image HD dans la div #ivLargeImage ou fallback
        const imageUrl = await page.evaluate(() => {
            // Priorité 1 : Image dans le viewer #ivLargeImage
            const largeImageDiv = document.querySelector('#ivLargeImage');
            if (largeImageDiv) {
                const img = largeImageDiv.querySelector('img');
                if (img && img.src) {
                    return img.src;
                }
            }

            // Priorité 2 : Image dans le conteneur d'image large
            const largeSelectors = [
                '#ivLargeImage img',
                '#imgTagWrapperId img',
                '#landingImage',
                '#imgBlkFront',
                '#ebooksImgBlkFront',
                '#main-image',
                '#imageBlock img.a-dynamic-image'
            ];

            for (const selector of largeSelectors) {
                const img = document.querySelector(selector);
                if (img && img.src && (img.src.includes('images-amazon') || img.src.includes('media-amazon'))) {
                    return img.src;
                }
            }

            return null;
        });

        // Améliorer la qualité de l'URL si trouvée
        let finalImageUrl = imageUrl;
        if (finalImageUrl) {
            // Supprimer les suffixes de redimensionnement Amazon pour obtenir l'image originale
            finalImageUrl = finalImageUrl.replace(/\._[A-Z]+[0-9,_]+_\./, '.');
        }

        await browser.close();

        if (finalImageUrl) {
            console.log(`✅ SUCCESS: ${finalImageUrl}`);
            console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);
            res.json({ 
                success: true, 
                imageUrl: finalImageUrl,
                source: 'Amazon.fr',
                isbn,
                debug: {
                    screenshot: screenshotPath,
                    screenshotProduct,
                    mainImageClicked
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
                    screenshot: screenshotPath,
                    screenshotProduct,
                    mainImageClicked
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

// ===================== FONCTIONS DE RECHERCHE FACTORISÉES =====================
// Ces fonctions naviguent jusqu'à la fiche produit et extraient soit l'image, soit toutes les infos

// Fonction de recherche Amazon factorisée
async function searchAmazonByIsbn(browser, isbn, mode, createOptimizedPage) {
    const page = await createOptimizedPage();
    try {
        console.log(`🔍 [Amazon] Recherche ${mode}...`);
        await page.goto(`https://www.amazon.fr/s?k=${isbn}`, { waitUntil: 'domcontentloaded', timeout: 12000 });
        await new Promise(r => setTimeout(r, 1000));
        
        const amazonLink = await page.evaluate(() => {
            const selectors = [
                'div[data-component-type="s-search-result"] h2 a',
                'div[data-component-type="s-search-result"] a.a-link-normal[href*="/dp/"]',
                '.s-result-item a[href*="/dp/"]',
                'a[href*="/dp/"][class*="link"]'
            ];
            for (const sel of selectors) {
                const link = document.querySelector(sel);
                if (link && link.href) return link.href;
            }
            return null;
        });

        if (amazonLink) {
            console.log(`📖 [Amazon] Produit trouvé: ${amazonLink.substring(0, 80)}...`);
            await page.goto(amazonLink, { waitUntil: 'domcontentloaded', timeout: 12000 });
            await new Promise(r => setTimeout(r, 1000));

            if (mode === 'image') {
                // Cliquer sur l'image principale pour obtenir la grande version
                await page.evaluate(() => {
                    const selectors = ['#imgBlkFront', '#landingImage', '#ebooksImgBlkFront', '#imgTagWrapperId img'];
                    for (const sel of selectors) {
                        const img = document.querySelector(sel);
                        if (img) { img.click(); return; }
                    }
                });
                await new Promise(r => setTimeout(r, 2000));

                let imageUrl = await page.evaluate(() => {
                    const largeDiv = document.querySelector('#ivLargeImage');
                    if (largeDiv) {
                        const img = largeDiv.querySelector('img');
                        if (img && img.src) return img.src;
                    }
                    const selectors = ['#ivLargeImage img', '#landingImage', '#imgBlkFront', '#ebooksImgBlkFront'];
                    for (const sel of selectors) {
                        const img = document.querySelector(sel);
                        if (img && img.src && (img.src.includes('images-amazon') || img.src.includes('media-amazon'))) return img.src;
                    }
                    return null;
                });

                await page.close();
                if (imageUrl) {
                    imageUrl = imageUrl.replace(/\._[A-Z]+[0-9,_]+_\./, '.');
                    console.log(`✅ [Amazon] Image trouvée`);
                    return { url: imageUrl, source: 'Amazon', quality: 'high' };
                }
            } else {
                // Mode info : extraire toutes les infos
                const data = await scrapeAmazonInfo(page);
                await page.close();
                if (data && data.titre) {
                    data.sourceUrl = amazonLink;
                    data.source = 'Amazon';
                    console.log(`✅ [Amazon] "${data.titre}" - Prix: ${data.prix || 'N/A'}€ - Pages: ${data.pages || 'N/A'}`);
                    return data;
                } else {
                    console.log(`⚠️ [Amazon] Page trouvée mais pas d'infos exploitables`);
                }
            }
        } else {
            console.log(`⚠️ [Amazon] Aucun produit trouvé pour cet ISBN`);
        }
        await page.close();
    } catch (e) {
        console.log(`❌ [Amazon] ${e.message}`);
        await page.close().catch(() => {});
    }
    return null;
}

// Fonction de recherche Bedetheque factorisée
async function searchBedethequeByIsbn(browser, isbn, mode, createOptimizedPage) {
    const page = await createOptimizedPage();
    try {
        console.log(`🔍 [Bedetheque] Recherche ${mode}...`);
        await page.goto('https://www.bedetheque.com/search/albums', { waitUntil: 'domcontentloaded', timeout: 8000 });

        const formSubmitted = await page.evaluate((isbnVal) => {
            const isbnInput = document.querySelector('input[name="RechISBN"]');
            if (!isbnInput) return false;
            isbnInput.value = isbnVal;
            const form = isbnInput.closest('form');
            if (form) { form.submit(); return true; }
            return false;
        }, isbn);

        if (formSubmitted) {
            await page.waitForNavigation({ timeout: 8000 }).catch(() => {});

            const bdLink = await page.evaluate(() => {
                const link = document.querySelector('a[href*="bedetheque.com/BD-"]');
                return link ? link.href : null;
            });

            if (bdLink) {
                console.log(`📖 [Bedetheque] Produit trouvé: ${bdLink.substring(0, 60)}...`);
                await page.goto(bdLink, { waitUntil: 'domcontentloaded', timeout: 8000 });
                await new Promise(r => setTimeout(r, 1000));

                if (mode === 'image') {
                    const imageUrl = await page.evaluate(() => {
                        const bandeauImage = document.querySelector('div.bandeau-image.album');
                        if (bandeauImage) {
                            const imgEl = bandeauImage.querySelector('img[itemprop="image"]');
                            if (imgEl && imgEl.src) return imgEl.src;
                        }
                        const currentUrl = window.location.href;
                        const albumIdMatch = currentUrl.match(/-(\d+)\.html$/);
                        if (albumIdMatch) {
                            return `https://www.bedetheque.com/media/Couvertures/Couv_${albumIdMatch[1]}.jpg`;
                        }
                        return null;
                    });

                    await page.close();
                    if (imageUrl) {
                        console.log(`✅ [Bedetheque] Image trouvée`);
                        return { url: imageUrl, source: 'Bedetheque', quality: 'high' };
                    }
                } else {
                    const data = await scrapeBedetequeInfo(page, isbn);
                    await page.close();
                    if (data && data.titre) {
                        data.sourceUrl = bdLink;
                        data.source = 'Bedetheque';
                        console.log(`✅ [Bedetheque] "${data.titre}"`);
                        return data;
                    }
                }
            } else {
                console.log(`⚠️ [Bedetheque] Aucun résultat pour cet ISBN`);
            }
        }
        await page.close();
    } catch (e) {
        console.log(`❌ [Bedetheque] ${e.message}`);
        await page.close().catch(() => {});
    }
    return null;
}

// Fonction de recherche Decitre factorisée
async function searchDecitreByIsbn(browser, isbn, mode, createOptimizedPage) {
    const page = await createOptimizedPage();
    try {
        console.log(`🔍 [Decitre] Recherche ${mode}...`);
        await page.goto(`https://www.decitre.fr/rechercher/result?q=${isbn}`, { waitUntil: 'domcontentloaded', timeout: 10000 });
        await new Promise(r => setTimeout(r, 1000));

        const productLink = await page.evaluate(() => {
            const selectors = [
                '.product-list-item a.product-link',
                '.product-item a[href*="/livres/"]',
                'a[href*="decitre.fr/livres/"]'
            ];
            for (const sel of selectors) {
                const link = document.querySelector(sel);
                if (link && link.href) return link.href;
            }
            return null;
        });

        if (productLink) {
            console.log(`📖 [Decitre] Produit trouvé`);
            await page.goto(productLink, { waitUntil: 'domcontentloaded', timeout: 10000 });
            await new Promise(r => setTimeout(r, 1000));

            if (mode === 'image') {
                const imageUrl = await page.evaluate(() => {
                    // Même sélecteurs que scrapeDecitreInfo pour l'image
                    const selectors = [
                        'section.product-summary img[src*="di-static"]',
                        '.product-image img[src*="di-static"]',
                        'img[src*="products-images"]',
                        '.product-gallery img',
                        'img[itemprop="image"]'
                    ];
                    for (const sel of selectors) {
                        const img = document.querySelector(sel);
                        if (img) {
                            const src = img.getAttribute('data-src') || img.getAttribute('data-zoom-image') || img.src;
                            if (src && !src.includes('placeholder') && src.length > 10) return src;
                        }
                    }
                    return null;
                });

                await page.close();
                if (imageUrl) {
                    console.log(`✅ [Decitre] Image trouvée`);
                    return { url: imageUrl, source: 'Decitre', quality: 'high' };
                }
            } else {
                const data = await scrapeDecitreInfo(page);
                await page.close();
                if (data && data.titre) {
                    data.sourceUrl = productLink;
                    data.source = 'Decitre';
                    console.log(`✅ [Decitre] "${data.titre}"`);
                    return data;
                }
            }
        } else {
            console.log(`⚠️ [Decitre] Aucun produit trouvé`);
        }
        await page.close();
    } catch (e) {
        console.log(`❌ [Decitre] ${e.message}`);
        await page.close().catch(() => {});
    }
    return null;
}

// Route pour scraper les IMAGES depuis tous les sites - VERSION FACTORISÉE
app.get('/scrape/all', authenticate, async (req, res) => {
    const { isbn } = req.query;
    
    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    const startTime = Date.now();
    console.log(`\n[${new Date().toISOString()}] === SCRAPING IMAGES ===`);
    console.log(`ISBN: ${isbn}`);

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
                '--disable-gpu', '--disable-extensions', '--disable-background-networking',
                '--disable-default-apps', '--disable-sync', '--disable-translate',
                '--metrics-recording-only', '--mute-audio', '--no-first-run',
                '--safebrowsing-disable-auto-update'
            ]
        });

        const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        // Fonction pour créer une page optimisée
        function createOptimizedPage(browser) {
            return (async () => {
                const page = await browser.newPage();
                await page.setUserAgent(userAgent);
                await page.setViewport({ width: 1280, height: 720 });
                await page.setRequestInterception(true);
                page.on('request', (request) => {
                    const type = request.resourceType();
                    if (['stylesheet', 'font', 'media', 'websocket', 'manifest', 'other'].includes(type)) {
                        request.abort();
                    } else {
                        request.continue();
                    }
                });
                return page;
            })();
        }

        // EXÉCUTION PARALLÈLE - mode 'image' (Excalibur exclu pour les images)
        const scraperPromises = [
            searchAmazonByIsbn(browser, isbn, 'image', () => createOptimizedPage(browser)),
            searchBedethequeByIsbn(browser, isbn, 'image', () => createOptimizedPage(browser)),
            searchDecitreByIsbn(browser, isbn, 'image', () => createOptimizedPage(browser))
        ];

        const allResults = await Promise.allSettled(scraperPromises);
        
        const results = allResults
            .filter(r => r.status === 'fulfilled' && r.value !== null)
            .map(r => r.value);

        await browser.close();

        const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);
        console.log(`\n📊 Total: ${results.length} image(s) trouvée(s) en ${elapsed}s`);
        console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);

        res.json({
            success: true,
            isbn,
            images: results,
            count: results.length,
            elapsed: `${elapsed}s`
        });

    } catch (error) {
        console.error(`❌ ERREUR: ${error.message}`);
        if (browser) await browser.close();
        res.status(500).json({ success: false, error: error.message });
    }
});

// Route pour scraper les INFORMATIONS d'un livre depuis une URL
app.get('/scrape/info', authenticate, async (req, res) => {
    const { url } = req.query;

    if (!url) {
        return res.status(400).json({ error: 'URL parameter is required' });
    }

    let browser;
    try {
        console.log(`\n[${new Date().toISOString()}] === SCRAPE INFO ===`);
        console.log(`URL: ${url}`);

        // Cultura nécessite des options spéciales pour contourner l'anti-bot
        const launchOptions = {
            headless: 'new',
            args: [
                '--no-sandbox', 
                '--disable-setuid-sandbox', 
                '--disable-dev-shm-usage', 
                '--window-size=1920x1080',
                '--disable-blink-features=AutomationControlled'
            ]
        };

        browser = await puppeteer.launch(launchOptions);

        const page = await browser.newPage();
        
        // Masquer les signes d'automatisation
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
            Object.defineProperty(navigator, 'languages', { get: () => ['fr-FR', 'fr', 'en-US', 'en'] });
            window.chrome = { runtime: {} };
        });
        
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1920, height: 1080 });
        
        // Ajouter des headers supplémentaires
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8'
        });

        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 3000));

        let data = null;

        if (url.includes('amazon')) {
            data = await scrapeAmazonInfo(page);
        } else if (url.includes('bedetheque') || url.includes('bdgest')) {
            data = await scrapeBedetequeInfo(page);
        } else if (url.includes('decitre')) {
            data = await scrapeDecitreInfo(page);
        } else if (url.includes('excalibur') || url.includes('excaliburcomics')) {
            data = await scrapeExcaliburInfo(page);
        } else {
            await browser.close();
            return res.json({ success: false, message: 'Site non supporté. Sites autorisés: Amazon, Bedetheque, Decitre, Excalibur Comics' });
        }
        await browser.close();

        if (data && data.titre) {
            data.sourceUrl = url;
            console.log(`✅ INFO trouvées: ${data.titre}`);
            res.json({ success: true, data });
        } else {
            console.log('❌ Aucune info trouvée');
            res.json({ success: false, message: 'Impossible de récupérer les informations' });
        }

    } catch (error) {
        console.error(`❌ ERREUR: ${error.message}`);
        if (browser) await browser.close();
        res.status(500).json({ success: false, error: error.message });
    }
});

// Route pour rechercher un livre par ISBN via Puppeteer (tous les sites) - VERSION FACTORISÉE
app.get('/scrape/search', authenticate, async (req, res) => {
    const { isbn, mode } = req.query;
    // mode=fast : retourne dès le premier résultat trouvé
    // mode=all (défaut) : attend tous les résultats

    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    const fastMode = mode === 'fast';
    const startTime = Date.now();
    console.log(`\n[${new Date().toISOString()}] === SEARCH ISBN (${fastMode ? 'FAST' : 'ALL'}) ===`);
    console.log(`ISBN: ${isbn}`);

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
                '--disable-gpu', '--disable-extensions', '--disable-background-networking',
                '--disable-default-apps', '--disable-sync', '--disable-translate',
                '--metrics-recording-only', '--mute-audio', '--no-first-run',
                '--safebrowsing-disable-auto-update'
            ]
        });

        const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        // Fonction pour créer une page optimisée (bloque les images pour les infos)
        function createOptimizedPage(browser) {
            return (async () => {
                const page = await browser.newPage();
                await page.setUserAgent(userAgent);
                await page.setViewport({ width: 1280, height: 720 });
                await page.setRequestInterception(true);
                page.on('request', (request) => {
                    const type = request.resourceType();
                    if (['image', 'stylesheet', 'font', 'media', 'websocket', 'manifest', 'other'].includes(type)) {
                        request.abort();
                    } else {
                        request.continue();
                    }
                });
                return page;
            })();
        }

        let allResults = [];

        // PRIORITÉ AMAZON : On attend d'abord Amazon, puis les autres en parallèle
        console.log(`⏳ Attente prioritaire d'Amazon...`);
        const amazonResult = await searchAmazonByIsbn(browser, isbn, 'info', () => createOptimizedPage(browser));
        
        if (amazonResult) {
            allResults.push(amazonResult);
            console.log(`✅ Amazon a répondu en premier avec des données`);
        } else {
            console.log(`⚠️ Amazon n'a pas trouvé de résultat`);
        }

        // Ensuite, lancer les autres sites en parallèle pour compléter
        if (!fastMode || !amazonResult) {
            console.log(`🔄 Recherche sur les autres sites...`);
            const otherPromises = [
                searchBedethequeByIsbn(browser, isbn, 'info', () => createOptimizedPage(browser)),
                searchDecitreByIsbn(browser, isbn, 'info', () => createOptimizedPage(browser))
            ];

            const settledResults = await Promise.allSettled(otherPromises);
            
            const otherResults = settledResults
                .filter(r => r.status === 'fulfilled' && r.value !== null)
                .map(r => r.value);

            allResults = [...allResults, ...otherResults];
        }

        await browser.close();

        const elapsed = ((Date.now() - startTime) / 1000).toFixed(2);

        if (allResults.length > 0) {
            console.log(`\n✅ ${allResults.length} résultat(s) en ${elapsed}s`);
            res.json({ success: true, data: allResults[0], allResults, elapsed: `${elapsed}s` });
        } else {
            console.log(`❌ Aucun résultat (${elapsed}s)`);
            res.json({ success: false, message: 'Aucun résultat trouvé pour cet ISBN', elapsed: `${elapsed}s` });
        }

    } catch (error) {
        console.error(`❌ ERREUR: ${error.message}`);
        if (browser) await browser.close();
        res.status(500).json({ success: false, error: error.message });
    }
});

// ===================== FONCTIONS DE SCRAPING INFO =====================

async function scrapeAmazonInfo(page) {
    return await page.evaluate(() => {
        const data = { titre: null, tome: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, prix: null, categories: [], source: 'Amazon' };

        // Mots à ignorer dans les noms d'auteurs
        const authorBlacklist = ['Afficher', 'Voir', 'plus', 'Auteur', 'Illustrateur', 'Traducteur', 'Contributeur', 'Préface', 'Scénariste', 'Dessinateur', 'Coloriste', 'Avec la contribution de'];

        // Titre
        const titleEl = document.querySelector('#productTitle, #ebooksProductTitle');
        if (titleEl) {
            data.titre = titleEl.textContent.trim();
            // Extraire le numéro de tome depuis le titre (ex: "Tome 31", "- Tome 5", "T05", "T5", "Vol. 3")
            const tomeMatch = data.titre.match(/(?:Tome|T|Vol\.?|Volume)[\s\-]*(\d+)/i);
            if (tomeMatch) {
                data.tome = parseInt(tomeMatch[1]);
            }
        }

        // Auteurs — parcourir les spans .author pour extraire le nom et filtrer les rôles
        const authorSpans = document.querySelectorAll('#bylineInfo .author');
        authorSpans.forEach(span => {
            const link = span.querySelector('a.a-link-normal');
            if (link) {
                const name = link.textContent.trim();
                if (name && name.length > 1 && !authorBlacklist.some(b => name.includes(b)) && !data.auteurs.includes(name)) {
                    data.auteurs.push(name);
                }
            }
        });
        // Fallback auteur : liens directs
        if (data.auteurs.length === 0) {
            document.querySelectorAll('.author a.a-link-normal, .contributorNameID, #bylineInfo a.a-link-normal').forEach(el => {
                const name = el.textContent.trim();
                if (name && name.length > 1 && !authorBlacklist.some(b => name.includes(b)) && !data.auteurs.includes(name)) {
                    data.auteurs.push(name);
                }
            });
        }

        // Détails produit dans detailBullets_feature_div (liste à puces avec spans)
        const detailBullets = document.querySelectorAll('#detailBullets_feature_div .a-list-item');
        detailBullets.forEach(item => {
            const spans = item.querySelectorAll('span span');
            if (spans.length >= 2) {
                const label = spans[0].textContent.replace(/\u200F|\u200E/g, '').replace(/:/g, '').trim().toLowerCase();
                const value = spans[1].textContent.replace(/\u200F|\u200E/g, '').trim();
                
                if (label.includes('isbn-13')) {
                    data.isbn = value.replace(/-/g, '').replace(/\s/g, '');
                }
                if (label.includes('isbn-10') && !data.isbn) {
                    data.isbn = value.replace(/\s/g, '');
                }
                if (label.includes('diteur') || label.includes('publisher')) {
                    // Format: "Éditeur (date)" - extraire les deux
                    const edMatch = value.match(/^(.+?)\s*\(/);
                    data.editeur = edMatch ? edMatch[1].trim() : value.split('(')[0].trim();
                }
                if (label.includes('date de publication') || label.includes('publication date')) {
                    // Format: "8 novembre 2023" - extraire l'année
                    const yearMatch = value.match(/(\d{4})/);
                    if (yearMatch) data.annee = parseInt(yearMatch[1]);
                }
                if (label.includes('pages') || label.includes('nombre de pages')) {
                    const match = value.match(/(\d+)/);
                    if (match) data.pages = parseInt(match[1]);
                }
            }
        });

        // Fallback: Détails dans le tableau alternatif (format tableau)
        const techRows = document.querySelectorAll('#productDetails_techSpec_section_1 tr, #productDetails_detailBullets_sections1 tr');
        techRows.forEach(row => {
            const label = row.querySelector('th');
            const value = row.querySelector('td');
            if (!label || !value) return;
            const labelText = label.textContent.trim().toLowerCase();
            const valueText = value.textContent.trim().replace(/\u200F|\u200E/g, '');
            
            if (labelText.includes('isbn-13') && !data.isbn) data.isbn = valueText.replace(/-/g, '').replace(/\s/g, '');
            if (labelText.includes('isbn-10') && !data.isbn) data.isbn = valueText.replace(/\s/g, '');
            if ((labelText.includes('diteur') || labelText.includes('publisher')) && !data.editeur) {
                data.editeur = valueText.replace(/\(.*?\)/g, '').trim();
            }
            if (labelText.includes('date') && !data.annee) {
                const yearMatch = valueText.match(/(\d{4})/);
                if (yearMatch) data.annee = parseInt(yearMatch[1]);
            }
            if (labelText.includes('pages') && !data.pages) {
                const match = valueText.match(/(\d+)/);
                if (match) data.pages = parseInt(match[1]);
            }
        });

        // Sous-titre format (Broché, Relié, etc.) — peut contenir le nb de pages
        if (!data.pages) {
            const subtitleEl = document.querySelector('#productSubtitle, .a-size-medium.a-color-secondary');
            if (subtitleEl) {
                const match = subtitleEl.textContent.match(/(\d+)\s*pages/i);
                if (match) data.pages = parseInt(match[1]);
            }
        }

        // Description/Résumé — Priorité 1: div avec classe a-expander-content expanded
        const expandedDesc = document.querySelector('.a-expander-content.a-expander-partial-collapse-content.a-expander-content-expanded');
        if (expandedDesc) {
            data.resume = expandedDesc.textContent.trim();
        }
        // Fallback description
        if (!data.resume) {
            const descSelectors = [
                '#bookDescription_feature_div .a-expander-content',
                'div[data-a-expander-name="book_description_expander"] .a-expander-content',
                '#book_description_expander',
                '#bookDescription_feature_div noscript div',
                '#productDescription .content',
                '#productDescription'
            ];
            for (const sel of descSelectors) {
                const el = document.querySelector(sel);
                if (el && el.textContent.trim().length > 20) {
                    data.resume = el.textContent.trim();
                    break;
                }
            }
        }
        // Nettoyer "En lire plus" à la fin du résumé
        if (data.resume) {
            data.resume = data.resume.replace(/\s*En lire plus\s*$/i, '').trim();
        }

        // Image HD — Priorité 1 : extraire depuis les scripts JSON embarqués (colorImages / imageGalleryData)
        const allScripts = document.querySelectorAll('script:not([src])');
        for (const script of allScripts) {
            const content = script.textContent;
            // colorImages JSON contient les URLs haute qualité
            const colorImagesMatch = content.match(/'colorImages'\s*:\s*\{\s*'initial'\s*:\s*(\[[\s\S]*?\])\s*\}/);
            if (colorImagesMatch) {
                try {
                    const images = JSON.parse(colorImagesMatch[1].replace(/'/g, '"'));
                    if (images[0] && images[0].hiRes) {
                        data.image = images[0].hiRes;
                        break;
                    }
                    if (images[0] && images[0].large) {
                        data.image = images[0].large;
                        break;
                    }
                } catch(e) { /* parse error, continue */ }
            }
            // Fallback: chercher hiRes directement
            const hiResMatch = content.match(/"hiRes"\s*:\s*"([^"]+)"/);
            if (hiResMatch) { data.image = hiResMatch[1].replace(/\\\//g, '/'); break; }
            const largeMatch = content.match(/"large"\s*:\s*"([^"]+)"/);
            if (largeMatch) { data.image = largeMatch[1].replace(/\\\//g, '/'); break; }
        }
        // Fallback: image tag directe
        if (!data.image) {
            const imgEl = document.querySelector('#landingImage, #imgBlkFront, #ebooksImgBlkFront');
            if (imgEl) {
                data.image = imgEl.getAttribute('data-old-hires') || imgEl.src;
                if (data.image) data.image = data.image.replace(/\._[A-Z]+[0-9,_]+_\./, '.');
            }
        }

        // Prix — Priorité 1: span#apex-pricetopay-accessibility-label ou prix dans corePrice
        const priceSelectors = [
            '#apex_desktop span.a-price .a-offscreen',
            '#corePrice_feature_div .a-price .a-offscreen',
            '#apex_desktop_newAccordionRow .a-price .a-offscreen',
            '#price .a-price .a-offscreen',
            '.a-price[data-a-size="xl"] .a-offscreen',
            '.a-price[data-a-size="l"] .a-offscreen',
            '#tmmSwatches .a-button-selected .a-color-price',
            '.swatchElement.selected .a-color-price',
            '.a-color-price'
        ];
        for (const sel of priceSelectors) {
            const priceEl = document.querySelector(sel);
            if (priceEl) {
                const priceText = priceEl.textContent.trim();
                // Extraire le prix (format: "17,50 €" ou "17.50€")
                const match = priceText.match(/([\d]+[,\.][\d]{2})/);
                if (match) {
                    data.prix = parseFloat(match[1].replace(',', '.'));
                    break;
                }
            }
        }

        // Catégories — breadcrumb
        const breadcrumbLinks = document.querySelectorAll('#wayfinding-breadcrumbs_feature_div a, .a-breadcrumb a');
        breadcrumbLinks.forEach(link => {
            const cat = link.textContent.trim();
            if (cat && cat.length > 1 && !data.categories.includes(cat) && !/Retour|Accueil|Amazon/i.test(cat)) {
                data.categories.push(cat);
            }
        });

        return data;
    });
}

async function scrapeDecitreInfo(page) {
    return await page.evaluate(() => {
        const data = { titre: null, tome: null, auteurs: [], editeur: null, collection: null, isbn: null, annee: null, pages: null, resume: null, image: null, prix: null, categories: [], source: 'Decitre' };

        const productSummary = document.querySelector('section.product-summary');
        
        if (productSummary) {
            // Titre : h1.title (attention aux br, remplacer par espace)
            const titleEl = productSummary.querySelector('h1.title');
            if (titleEl) {
                // Remplacer les <br> par des espaces
                data.titre = titleEl.innerHTML.replace(/<br\s*\/?>/gi, ' ').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
                // Extraire le tome depuis le titre
                const tomeMatch = data.titre.match(/(?:Tome|T|Vol\.?)[\s]*(\d+)/i);
                if (tomeMatch) data.tome = parseInt(tomeMatch[1]);
            }

            // Auteurs : tous les span.product-creator__name
            productSummary.querySelectorAll('span.product-creator__name').forEach(el => {
                const name = el.textContent.trim();
                if (name && name.length > 1 && !data.auteurs.includes(name)) {
                    data.auteurs.push(name);
                }
            });

            // Caractéristiques dans div.product-summary-caracteristics ul.list
            const caracList = productSummary.querySelector('div.product-summary-caracteristics ul.list');
            if (caracList) {
                caracList.querySelectorAll('li').forEach(li => {
                    const spans = li.querySelectorAll('span');
                    if (spans.length >= 2) {
                        const label = spans[0].textContent.trim();
                        const value = spans[1].textContent.trim();

                        if (label === 'ISBN') {
                            data.isbn = value.replace(/-/g, '');
                        }
                        if (label === 'Date de parution') {
                            // Format: dd/mm/yyyy
                            const match = value.match(/(\d{2})\/(\d{2})\/(\d{4})/);
                            if (match) {
                                data.annee = parseInt(match[3]);
                            }
                        }
                        if (label === 'Collection') {
                            data.collection = value;
                        }
                        if (label === 'Editeur' || label === 'Éditeur') {
                            data.editeur = value;
                        }
                        if (label === 'Nombre de pages') {
                            const match = value.match(/(\d+)/);
                            if (match) data.pages = parseInt(match[1]);
                        }
                    }
                });
            }

            // Prix de base : dans div.product-summary-formats, chercher a.product-button.link--active avec span "Album"
            const formatsDiv = productSummary.querySelector('div.product-summary-formats');
            if (formatsDiv) {
                const activeButtons = formatsDiv.querySelectorAll('a.product-button.link--active');
                activeButtons.forEach(btn => {
                    const spanText = btn.querySelector('span');
                    if (spanText && spanText.textContent.includes('Album')) {
                        const priceDiv = btn.querySelector('div.price');
                        if (priceDiv) {
                            const match = priceDiv.textContent.match(/([\d,\.]+)/);
                            if (match) data.prix = parseFloat(match[1].replace(',', '.'));
                        }
                    }
                });
            }
            // Fallback prix
            if (!data.prix) {
                const priceEl = productSummary.querySelector('.price, [itemprop="price"]');
                if (priceEl) {
                    const match = priceEl.textContent.match(/([\d,\.]+)/);
                    if (match) data.prix = parseFloat(match[1].replace(',', '.'));
                }
            }
        }

        // Résumé : div.real-text
        const resumeEl = document.querySelector('div.real-text');
        if (resumeEl) {
            data.resume = resumeEl.textContent.trim();
        }

        // Image : div.product-gallery__main-image img
        const galleryDiv = document.querySelector('div.product-gallery__main-image');
        if (galleryDiv) {
            const imgEl = galleryDiv.querySelector('img');
            if (imgEl) {
                data.image = imgEl.getAttribute('data-src') || imgEl.getAttribute('src');
            }
        }

        // Catégories depuis breadcrumb
        document.querySelectorAll('.breadcrumb a, .fil-ariane a').forEach(el => {
            const cat = el.textContent.trim();
            if (cat && cat.length > 1 && !data.categories.includes(cat)) data.categories.push(cat);
        });

        return data;
    });
}

async function scrapeExcaliburInfo(page) {
    return await page.evaluate(() => {
        const data = { titre: null, tome: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, prix: null, categories: ['Comics'], source: 'Excalibur Comics' };

        // Titre : dans section#main, p.product_name
        const mainSection = document.querySelector('section#main');
        if (mainSection) {
            const titleEl = mainSection.querySelector('p.product_name');
            if (titleEl) {
                data.titre = titleEl.textContent.trim();
                // Extraire le tome depuis le titre
                const tomeMatch = data.titre.match(/(?:Tome|T|Vol\.|Volume)[\s]*(\d+)/i);
                if (tomeMatch) data.tome = parseInt(tomeMatch[1]);
            }
        }

        // Caractéristiques dans div.product-features > div.row
        const productFeatures = document.querySelector('div.product-features');
        if (productFeatures) {
            const rows = productFeatures.querySelectorAll('div.row');
            rows.forEach(row => {
                const nameDiv = row.querySelector('div.name');
                const valueDiv = row.querySelector('div.value');
                
                if (nameDiv && valueDiv) {
                    const label = nameDiv.textContent.trim();
                    const value = valueDiv.textContent.trim();

                    // Auteurs : Scénariste(s) ou Dessinateur(s)
                    if (label.includes('Scénariste') || label.includes('Dessinateur')) {
                        valueDiv.querySelectorAll('a').forEach(a => {
                            const name = a.textContent.trim();
                            if (name && name.length > 1 && !data.auteurs.includes(name)) {
                                data.auteurs.push(name);
                            }
                        });
                    }

                    // ISBN/EAN
                    if (label.includes('EAN')) {
                        data.isbn = value.replace(/-/g, '');
                    }

                    // Date (format texte: "5 février 2025")
                    if (label.includes('Date')) {
                        const months = {
                            'janvier': '01', 'février': '02', 'mars': '03', 'avril': '04',
                            'mai': '05', 'juin': '06', 'juillet': '07', 'août': '08',
                            'septembre': '09', 'octobre': '10', 'novembre': '11', 'décembre': '12'
                        };
                        const match = value.match(/(\d{1,2})\s+(\w+)\s+(\d{4})/);
                        if (match) {
                            data.annee = parseInt(match[3]);
                        }
                    }

                    // Tome
                    if (label.includes('Tome')) {
                        const match = value.match(/(\d+)/);
                        if (match) data.tome = parseInt(match[1]);
                    }

                    // Pages
                    if (label.includes('Pages')) {
                        const match = value.match(/(\d+)/);
                        if (match) data.pages = parseInt(match[1]);
                    }
                }
            });
        }

        // Éditeur : div.product-manufacturer img alt
        const manufacturerDiv = document.querySelector('div.product-manufacturer');
        if (manufacturerDiv) {
            const img = manufacturerDiv.querySelector('img');
            if (img && img.alt) {
                data.editeur = img.alt.trim();
            }
        }

        // Prix : div.product-price span.price content
        const priceDiv = document.querySelector('div.product-price');
        if (priceDiv) {
            const priceSpan = priceDiv.querySelector('span.price');
            if (priceSpan) {
                const content = priceSpan.getAttribute('content');
                if (content) {
                    data.prix = parseFloat(content);
                } else {
                    const match = priceSpan.textContent.match(/([\d,\.]+)/);
                    if (match) data.prix = parseFloat(match[1].replace(',', '.'));
                }
            }
        }

        // Résumé : div.product-description p
        const descDiv = document.querySelector('div.product-description');
        if (descDiv) {
            const p = descDiv.querySelector('p');
            if (p) {
                data.resume = p.textContent.trim();
            }
        }

        // Image : div.product-cover img
        const coverDiv = document.querySelector('div.product-cover');
        if (coverDiv) {
            const img = coverDiv.querySelector('img');
            if (img) {
                data.image = img.getAttribute('data-src') || img.src;
            }
        }

        return data;
    });
}

async function scrapeBabelioInfo(page) {
    return await page.evaluate(() => {
        const data = { titre: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, source: 'Babelio' };

        // Titre
        const titleEl = document.querySelector('h1[itemprop="name"] a, h1[itemprop="name"], .livre_header_title');
        if (titleEl) data.titre = titleEl.textContent.trim();

        // Auteurs — utiliser a.livre_auteurs (spécifique à la fiche du livre)
        document.querySelectorAll('a.livre_auteurs').forEach(el => {
            const name = el.textContent.trim();
            if (name && name.length > 1 && !data.auteurs.includes(name)) data.auteurs.push(name);
        });

        // Infos dans le corps de la page (ISBN, éditeur, pages, date)
        const bodyText = document.body ? document.body.innerText : '';

        // ISBN
        const isbnMatch = bodyText.match(/(\d{13})/);
        if (isbnMatch) data.isbn = isbnMatch[1];

        // Pages
        const pagesMatch = bodyText.match(/(\d+)\s*pages/i);
        if (pagesMatch) data.pages = parseInt(pagesMatch[1]);

        // Date de parution
        const dateMatch = bodyText.match(/(\d{2}\/\d{2}\/(\d{4}))/);
        if (dateMatch) data.annee = parseInt(dateMatch[2]);

        // Éditeur — chercher dans la zone livre_refs ou les liens éditeur
        document.querySelectorAll('.livre_refs a, a[href*="/editeur/"]').forEach(el => {
            if (el.href && el.href.includes('/editeur/') && !data.editeur) {
                data.editeur = el.textContent.trim();
            }
        });

        // Résumé
        const descEl = document.querySelector('#d_bio, div[itemprop="description"]');
        if (descEl) data.resume = descEl.textContent.trim();

        // Image — préférer l'image de la couverture dans le header
        const headerImg = document.querySelector('.livre_header img[src*="images"]');
        if (headerImg && headerImg.src) {
            data.image = headerImg.src;
        }
        if (!data.image) {
            const metaImg = document.querySelector('meta[property="og:image"]');
            if (metaImg) data.image = metaImg.getAttribute('content');
        }
        if (!data.image) {
            const imgEl = document.querySelector('img[itemprop="image"], .couverture img');
            if (imgEl) data.image = imgEl.src;
        }

        return data;
    });
}

async function scrapeBedetequeInfo(page, searchedIsbn) {
    return await page.evaluate((searchedIsbn) => {
        const data = { titre: null, tome: null, auteurs: [], editeur: null, collection: null, isbn: null, annee: null, pages: null, resume: null, image: null, categories: ['BD'], source: 'Bedetheque' };

        // Titre : dans div.bandeau-info.album.panier, meta itemprop="name" + alternativeheadline
        const bandeauInfo = document.querySelector('div.bandeau-info.album.panier');
        if (bandeauInfo) {
            const nameMeta = bandeauInfo.querySelector('meta[itemprop="name"]');
            const altMeta = bandeauInfo.querySelector('meta[itemprop="alternativeheadline"]');
            if (nameMeta) {
                data.titre = nameMeta.getAttribute('content') || '';
                if (altMeta && altMeta.getAttribute('content')) {
                    const altContent = altMeta.getAttribute('content');
                    // Ne pas ajouter si c'est juste "Tome X"
                    if (!/^Tome\s*\d+$/i.test(altContent.trim())) {
                        data.titre += ' - ' + altContent;
                    }
                }
                data.titre = data.titre.trim();
            }
        }

        // Auteurs : dans div.tab_content_liste_albums ul.infos-albums, chercher "Scénario :" et "Dessin :"
        const infosAlbums = document.querySelector('div.tab_content_liste_albums ul.infos-albums');
        if (infosAlbums) {
            const allLis = infosAlbums.querySelectorAll('li');
            let currentLabel = null;
            
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                const text = li.textContent.trim();
                
                if (labelEl) {
                    const labelText = labelEl.textContent.trim();
                    if (labelText.includes('Scénario') || labelText.includes('Dessin')) {
                        currentLabel = labelText;
                        // Extraire le nom après le label (tout ce qui suit le label dans le li)
                        let authorText = text.replace(labelText, '').trim();
                        // Enlever les virgules des noms
                        authorText = authorText.replace(/,/g, '');
                        if (authorText && authorText.length > 1 && !data.auteurs.includes(authorText)) {
                            data.auteurs.push(authorText);
                        }
                    } else {
                        currentLabel = labelText;
                    }
                } else if (currentLabel && (currentLabel.includes('Scénario') || currentLabel.includes('Dessin'))) {
                    // Li sans label, lié au précédent type de label
                    let authorText = text.replace(/,/g, '').trim();
                    if (authorText && authorText.length > 1 && !data.auteurs.includes(authorText)) {
                        data.auteurs.push(authorText);
                    }
                } else {
                    currentLabel = null;
                }
            });

            // ISBN : label "EAN/ISBN : " puis span suivant
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('EAN/ISBN')) {
                    const spanEl = li.querySelector('span');
                    if (spanEl) {
                        data.isbn = spanEl.textContent.trim().replace(/-/g, '');
                    }
                }
            });

            // Année : label "Dépot légal : " - soit directement après (11/2025) soit dans un span (Parution le 21/11/2025)
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('Dépot légal')) {
                    const spanEl = li.querySelector('span');
                    if (spanEl) {
                        // Format: "(Parution le 21/11/2025)"
                        const match = spanEl.textContent.match(/(\d{2})\/(\d{2})\/(\d{4})/);
                        if (match) {
                            data.annee = parseInt(match[3]);
                        }
                    } else {
                        // Format: "11/2025" directement après le label
                        const text = li.textContent.replace(labelEl.textContent, '').trim();
                        const match = text.match(/(\d{2})\/(\d{4})/);
                        if (match) {
                            data.annee = parseInt(match[2]);
                        }
                    }
                }
            });

            // Collection (Série) : label "Série : " puis texte après
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('Série')) {
                    const text = li.textContent.replace(labelEl.textContent, '').trim();
                    if (text) data.collection = text;
                }
            });

            // Editeur : label "Editeur : " puis texte après
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('Editeur')) {
                    const text = li.textContent.replace(labelEl.textContent, '').trim();
                    if (text) data.editeur = text;
                }
            });

            // Tome : label "Tome : " puis texte après
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('Tome')) {
                    const text = li.textContent.replace(labelEl.textContent, '').trim();
                    const match = text.match(/(\d+)/);
                    if (match) data.tome = parseInt(match[1]);
                }
            });

            // Nombre de pages : label "Planches : " puis texte après
            allLis.forEach(li => {
                const labelEl = li.querySelector('label');
                if (labelEl && labelEl.textContent.includes('Planches')) {
                    const text = li.textContent.replace(labelEl.textContent, '').trim();
                    const match = text.match(/(\d+)/);
                    if (match) data.pages = parseInt(match[1]);
                }
            });
        }

        // Résumé : meta name="description" ou p#p-serie
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
            data.resume = metaDesc.getAttribute('content');
        }
        if (!data.resume) {
            const pSerie = document.querySelector('#p-serie');
            if (pSerie) data.resume = pSerie.textContent.trim();
        }

        // Image : dans div.bandeau-image.album, img itemprop="image"
        const bandeauImage = document.querySelector('div.bandeau-image.album');
        if (bandeauImage) {
            const imgEl = bandeauImage.querySelector('img[itemprop="image"]');
            if (imgEl) {
                data.image = imgEl.src;
            }
        }
        
        // Fallback image: construire depuis l'URL
        if (!data.image) {
            const currentUrl = window.location.href;
            const albumIdMatch = currentUrl.match(/-(\d+)\.html$/);
            if (albumIdMatch) {
                data.image = `https://www.bedetheque.com/media/Couvertures/Couv_${albumIdMatch[1]}.jpg`;
            }
        }

        return data;
    }, searchedIsbn);
}

// Démarrer le serveur
app.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Scraper service running on port ${PORT}`);
    console.log(`📝 API Key: ${API_KEY.substring(0, 8)}...`);
    console.log(`🔍 Test: curl "http://localhost:${PORT}/health"`);
});
