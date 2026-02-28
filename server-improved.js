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

    // 4. Amazon - Version améliorée avec navigation vers la page produit
    let browserAmazon;
    try {
        console.log(`\n🔍 Scraping Amazon (version améliorée)...`);
        
        browserAmazon = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
        });

        const pageAmazon = await browserAmazon.newPage();
        await pageAmazon.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        await pageAmazon.setRequestInterception(true);
        pageAmazon.on('request', (request) => {
            if (['stylesheet', 'font', 'media'].includes(request.resourceType())) {
                request.abort();
            } else {
                request.continue();
            }
        });

        // Recherche Amazon
        await pageAmazon.goto(`https://www.amazon.fr/s?k=${isbn}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Trouver le lien du premier produit
        const productLink = await pageAmazon.evaluate(() => {
            const selectors = [
                'div[data-component-type="s-search-result"] a.a-link-normal.s-no-outline',
                'div[data-component-type="s-search-result"] .a-link-normal[href*="/dp/"]',
                'div[data-component-type="s-search-result"] h2 a',
                '.s-result-item a[href*="/dp/"]'
            ];
            for (const selector of selectors) {
                const link = document.querySelector(selector);
                if (link && link.href) return link.href;
            }
            return null;
        });

        if (productLink) {
            console.log(`📖 Amazon: Navigation vers ${productLink}`);
            await pageAmazon.goto(productLink, { waitUntil: 'domcontentloaded', timeout: 15000 });
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Cliquer sur l'image principale
            await pageAmazon.evaluate(() => {
                const selectors = ['#imgBlkFront', '#landingImage', '#ebooksImgBlkFront', '#imgTagWrapperId img'];
                for (const selector of selectors) {
                    const img = document.querySelector(selector);
                    if (img) { img.click(); return; }
                }
            });
            await new Promise(resolve => setTimeout(resolve, 3000));

            // Récupérer l'image HD
            let imageUrl = await pageAmazon.evaluate(() => {
                // Priorité 1 : #ivLargeImage
                const largeDiv = document.querySelector('#ivLargeImage');
                if (largeDiv) {
                    const img = largeDiv.querySelector('img');
                    if (img && img.src) return img.src;
                }
                // Priorité 2 : fallback
                const selectors = ['#ivLargeImage img', '#landingImage', '#imgBlkFront', '#ebooksImgBlkFront'];
                for (const sel of selectors) {
                    const img = document.querySelector(sel);
                    if (img && img.src && (img.src.includes('images-amazon') || img.src.includes('media-amazon'))) return img.src;
                }
                return null;
            });

            if (imageUrl) {
                imageUrl = imageUrl.replace(/\._[A-Z]+[0-9,_]+_\./, '.');
                console.log(`✅ Amazon: ${imageUrl}`);
                results.push({ url: imageUrl, source: 'Amazon', quality: 'high' });
            } else {
                console.log(`❌ Amazon: Aucune image HD trouvée`);
            }
        } else {
            console.log(`❌ Amazon: Aucun produit trouvé`);
        }

        await browserAmazon.close();
    } catch (error) {
        console.error(`❌ Amazon Error:`, error.message);
        if (browserAmazon) await browserAmazon.close();
    }

    console.log(`\n📊 Total: ${results.length} image(s) trouvée(s)`);
    console.log(`[${new Date().toISOString()}] === FIN SCRAPING ===\n`);

    res.json({
        success: true,
        isbn,
        images: results,
        count: results.length
    });
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

        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--window-size=1920x1080']
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1920, height: 1080 });

        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 3000));

        let data = null;

        if (url.includes('amazon')) {
            data = await scrapeAmazonInfo(page);
        } else if (url.includes('fnac')) {
            data = await scrapeFnacInfo(page);
        } else if (url.includes('babelio')) {
            data = await scrapeBabelioInfo(page);
        } else if (url.includes('bedetheque') || url.includes('bdgest')) {
            data = await scrapeBedetequeInfo(page);
        } else {
            await browser.close();
            return res.json({ success: false, message: 'Site non supporté' });
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

// Route pour rechercher un livre par ISBN via Puppeteer (tous les sites)
app.get('/scrape/search', authenticate, async (req, res) => {
    const { isbn } = req.query;

    if (!isbn) {
        return res.status(400).json({ error: 'ISBN parameter is required' });
    }

    let browser;
    try {
        console.log(`\n[${new Date().toISOString()}] === SEARCH ISBN ===`);
        console.log(`ISBN: ${isbn}`);

        browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--window-size=1920x1080']
        });

        let allResults = [];

        // ===== 1. AMAZON =====
        try {
            console.log(`🔍 Recherche sur Amazon...`);
            const pageAmazon = await browser.newPage();
            await pageAmazon.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            await pageAmazon.setViewport({ width: 1920, height: 1080 });
            await pageAmazon.goto(`https://www.amazon.fr/s?k=${isbn}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
            await new Promise(r => setTimeout(r, 3000));

            const amazonLink = await pageAmazon.evaluate(() => {
                const selectors = [
                    'div[data-component-type="s-search-result"] a.a-link-normal.s-no-outline',
                    'div[data-component-type="s-search-result"] .a-link-normal[href*="/dp/"]',
                    'div[data-component-type="s-search-result"] h2 a',
                    '.s-result-item a[href*="/dp/"]'
                ];
                for (const sel of selectors) {
                    const link = document.querySelector(sel);
                    if (link && link.href) return link.href;
                }
                return null;
            });

            if (amazonLink) {
                console.log(`  📖 Amazon: produit trouvé → ${amazonLink}`);
                await pageAmazon.goto(amazonLink, { waitUntil: 'domcontentloaded', timeout: 20000 });
                await new Promise(r => setTimeout(r, 3000));
                const data = await scrapeAmazonInfo(pageAmazon);
                if (data && data.titre) {
                    data.sourceUrl = amazonLink;
                    data.source = 'Amazon';
                    allResults.push(data);
                    console.log(`  ✅ Amazon: "${data.titre}"`);
                } else {
                    console.log(`  ⚠️ Amazon: page trouvée mais pas d'infos exploitables`);
                }
            } else {
                console.log(`  ❌ Amazon: aucun résultat`);
            }
            await pageAmazon.close();
        } catch (e) {
            console.log(`  ❌ Amazon: erreur → ${e.message}`);
        }

        // ===== 2. BABELIO (URL directe /isbn/) =====
        try {
            console.log(`🔍 Recherche sur Babelio...`);
            const pageBabelio = await browser.newPage();
            await pageBabelio.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            await pageBabelio.setViewport({ width: 1920, height: 1080 });
            // Babelio a une URL directe /isbn/ qui redirige vers la fiche
            await pageBabelio.goto(`https://www.babelio.com/isbn/${isbn}`, { waitUntil: 'networkidle2', timeout: 20000 });
            await new Promise(r => setTimeout(r, 2000));

            const babelioUrl = pageBabelio.url();
            // Vérifier qu'on est bien sur une fiche livre (pas une page d'erreur)
            const isBookPage = babelioUrl.includes('/livres/');
            if (isBookPage) {
                console.log(`  📖 Babelio: redirigé vers ${babelioUrl}`);
                const data = await scrapeBabelioInfo(pageBabelio);
                if (data && data.titre) {
                    data.sourceUrl = babelioUrl;
                    data.source = 'Babelio';
                    allResults.push(data);
                    console.log(`  ✅ Babelio: "${data.titre}"`);
                } else {
                    console.log(`  ⚠️ Babelio: page trouvée mais pas d'infos exploitables`);
                }
            } else {
                console.log(`  ❌ Babelio: ISBN non trouvé (URL: ${babelioUrl})`);
            }
            await pageBabelio.close();
        } catch (e) {
            console.log(`  ❌ Babelio: erreur → ${e.message}`);
        }

        // ===== 3. BEDETHEQUE (formulaire avec CSRF) =====
        try {
            console.log(`🔍 Recherche sur Bedetheque...`);
            const pageBd = await browser.newPage();
            await pageBd.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            await pageBd.setViewport({ width: 1920, height: 1080 });
            // Aller d'abord sur la page de recherche pour obtenir le CSRF token
            await pageBd.goto('https://www.bedetheque.com/search/albums', { waitUntil: 'networkidle2', timeout: 20000 });
            await new Promise(r => setTimeout(r, 2000));

            // Accepter les cookies si présents
            await pageBd.evaluate(() => {
                const btns = Array.from(document.querySelectorAll('button, a')).filter(b => /fermer|accepter|j.accepte/i.test(b.textContent));
                if (btns.length > 0) btns[0].click();
            });
            await new Promise(r => setTimeout(r, 1000));

            // Remplir le champ ISBN et soumettre le formulaire
            const formSubmitted = await pageBd.evaluate((isbnVal) => {
                const isbnInput = document.querySelector('input[name="RechISBN"]');
                if (!isbnInput) return false;
                isbnInput.value = isbnVal;
                const form = isbnInput.closest('form');
                if (form) { form.submit(); return true; }
                return false;
            }, isbn);

            if (formSubmitted) {
                await pageBd.waitForNavigation({ timeout: 15000 }).catch(() => {});
                await new Promise(r => setTimeout(r, 3000));

                // Chercher un lien vers une fiche BD
                const bdLink = await pageBd.evaluate(() => {
                    const links = Array.from(document.querySelectorAll('a'));
                    const bdLink = links.find(a => a.href && a.href.match(/bedetheque\.com\/BD-/));
                    return bdLink ? bdLink.href : null;
                });

                if (bdLink) {
                    console.log(`  📖 Bedetheque: produit trouvé → ${bdLink}`);
                    await pageBd.goto(bdLink, { waitUntil: 'domcontentloaded', timeout: 20000 });
                    await new Promise(r => setTimeout(r, 3000));
                    const data = await scrapeBedetequeInfo(pageBd, isbn);
                    if (data && data.titre) {
                        data.sourceUrl = bdLink;
                        data.source = 'Bedetheque';
                        allResults.push(data);
                        console.log(`  ✅ Bedetheque: "${data.titre}"`);
                    } else {
                        console.log(`  ⚠️ Bedetheque: page trouvée mais pas d'infos exploitables`);
                    }
                } else {
                    console.log(`  ❌ Bedetheque: aucun résultat pour cet ISBN`);
                }
            } else {
                console.log(`  ❌ Bedetheque: formulaire non trouvé`);
            }
            await pageBd.close();
        } catch (e) {
            console.log(`  ❌ Bedetheque: erreur → ${e.message}`);
        }

        await browser.close();

        if (allResults.length > 0) {
            console.log(`\n✅ ${allResults.length} résultat(s) trouvé(s) au total`);
            res.json({ success: true, data: allResults[0], allResults });
        } else {
            console.log('❌ Aucune info trouvée sur aucun site');
            res.json({ success: false, message: 'Aucun résultat trouvé pour cet ISBN' });
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
        const data = { titre: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, source: 'Amazon' };

        // Mots à ignorer dans les noms d'auteurs
        const authorBlacklist = ['Afficher', 'Voir', 'plus', 'Auteur', 'Illustrateur', 'Traducteur', 'Contributeur', 'Préface', 'Scénariste', 'Dessinateur', 'Coloriste', 'Avec la contribution de'];

        // Titre
        const titleEl = document.querySelector('#productTitle, #ebooksProductTitle');
        if (titleEl) data.titre = titleEl.textContent.trim();

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

        // Détails produit (liste à puces)
        const detailRows = document.querySelectorAll('#detailBullets_feature_div li, #productDetailsTable tr, .detail-bullet-list .a-list-item');
        detailRows.forEach(row => {
            const text = row.textContent.replace(/\u200F|\u200E/g, '').replace(/\s+/g, ' ');
            if (/ISBN-13/i.test(text)) {
                const match = text.match(/(\d[\d-]{11,16}\d)/);
                if (match) data.isbn = match[1].replace(/-/g, '');
            }
            if (/ISBN-10/i.test(text) && !data.isbn) {
                const match = text.match(/(\d{10})/);
                if (match) data.isbn = match[1];
            }
            if (/diteur|Publisher/i.test(text)) {
                const match = text.match(/(?:diteur|Publisher)\s*[:\s]\s*(.+?)(?:\(|;|\s*$)/i);
                if (match) {
                    data.editeur = match[1].trim().replace(/\u200F|\u200E/g, '').trim();
                    // Extraire l'année entre parenthèses
                    const yearMatch = text.match(/\((\d{1,2}\s+\w+\s+)?(\d{4})\)/);
                    if (yearMatch && !data.annee) data.annee = parseInt(yearMatch[2]);
                }
            }
            if (/pages/i.test(text)) {
                const match = text.match(/(\d+)\s*pages/i);
                if (match) data.pages = parseInt(match[1]);
            }
        });

        // Détails dans le tableau alternatif (format tableau)
        const techRows = document.querySelectorAll('#productDetails_techSpec_section_1 tr, #productDetails_detailBullets_sections1 tr');
        techRows.forEach(row => {
            const label = row.querySelector('th');
            const value = row.querySelector('td');
            if (!label || !value) return;
            const labelText = label.textContent.trim();
            const valueText = value.textContent.trim().replace(/\u200F|\u200E/g, '');
            
            if (/ISBN-13/i.test(labelText)) data.isbn = valueText.replace(/-/g, '').replace(/\s/g, '').trim();
            if (/ISBN-10/i.test(labelText) && !data.isbn) data.isbn = valueText.replace(/\s/g, '').trim();
            if (/diteur|Publisher/i.test(labelText)) {
                data.editeur = valueText.replace(/\(.*?\)/g, '').trim();
                const yearMatch = valueText.match(/\((\d{4})\)/);
                if (yearMatch) data.annee = parseInt(yearMatch[1]);
            }
            if (/pages/i.test(labelText)) {
                const match = valueText.match(/(\d+)/);
                if (match) data.pages = parseInt(match[1]);
            }
            if (/Dimensions|Poids/i.test(labelText)) { /* skip */ }
        });

        // Sous-titre format (Broché, Relié, etc.) — peut contenir le nb de pages
        if (!data.pages) {
            const subtitleEl = document.querySelector('#productSubtitle, .a-size-medium.a-color-secondary');
            if (subtitleEl) {
                const match = subtitleEl.textContent.match(/(\d+)\s*pages/i);
                if (match) data.pages = parseInt(match[1]);
            }
        }

        // Résumé — essayer plusieurs sélecteurs
        const descSelectors = [
            '#bookDescription_feature_div .a-expander-content span',
            'div[data-a-expander-name="book_description_expander"] div.a-expander-content',
            '#bookDescription_feature_div .a-expander-content',
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
                // data-old-hires a souvent la meilleure qualité
                data.image = imgEl.getAttribute('data-old-hires') || imgEl.src;
                if (data.image) data.image = data.image.replace(/\._[A-Z]+[0-9,_]+_\./, '.');
            }
        }

        return data;
    });
}

async function scrapeFnacInfo(page) {
    return await page.evaluate(() => {
        const data = { titre: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, source: 'Fnac' };

        // Titre
        const titleEl = document.querySelector('.f-productHeader-Title, h1.productTitle');
        if (titleEl) data.titre = titleEl.textContent.trim();

        // Auteurs
        document.querySelectorAll('.f-productHeader-Author a, .authorName a').forEach(el => {
            const name = el.textContent.trim();
            if (name && !data.auteurs.includes(name)) data.auteurs.push(name);
        });

        // Caractéristiques
        document.querySelectorAll('.f-productCharacteristics li, .caracteristiques li').forEach(li => {
            const text = li.textContent;
            if (/EAN/i.test(text)) {
                const match = text.match(/(\d{13})/);
                if (match) data.isbn = match[1];
            }
            if (/diteur/i.test(text)) {
                const val = text.split(':').pop();
                if (val) data.editeur = val.trim();
            }
            if (/Date de parution/i.test(text)) {
                const match = text.match(/(\d{4})/);
                if (match) data.annee = parseInt(match[1]);
            }
            if (/pages/i.test(text)) {
                const match = text.match(/(\d+)\s*pages/i);
                if (match) data.pages = parseInt(match[1]);
            }
        });

        // Résumé
        const descEl = document.querySelector('.f-productSynopsis, .productStrate--synopsis');
        if (descEl) data.resume = descEl.textContent.trim();

        // Image
        const imgEl = document.querySelector('.f-productVisuals-mainMedia img, .productStrate--image img');
        if (imgEl) data.image = imgEl.getAttribute('data-src') || imgEl.src;

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
        const data = { titre: null, auteurs: [], editeur: null, isbn: null, annee: null, pages: null, resume: null, image: null, categories: ['BD'], source: 'Bedetheque' };

        // Titre : combiner h1 (série) et h2 (album)
        const h2El = document.querySelector('h2');
        const h1El = document.querySelector('h1');
        if (h2El) {
            // h2 contient "39.    Les schtroumpfs et la tempête blanche" — nettoyer espaces puis numéro
            data.titre = h2El.textContent.replace(/\s+/g, ' ').trim().replace(/^\d+\.\s*/, '').trim();
        } else if (h1El) {
            data.titre = h1El.textContent.replace(/\s+/g, ' ').trim();
        }

        // Auteurs — extraire depuis le texte "Une BD de X et Y" ou "Une BD de X, Y et Z"
        const bodyText = document.body ? document.body.innerText : '';
        const auteurMatch = bodyText.match(/Une BD de\s+(.+?)(?:\s+-\s+|\s*$)/m);
        if (auteurMatch) {
            // Séparer par " et " et ","
            const auteursStr = auteurMatch[1].trim();
            auteursStr.split(/\s+et\s+|,\s*/).forEach(name => {
                name = name.trim();
                if (name && name.length > 1 && !data.auteurs.includes(name)) {
                    data.auteurs.push(name);
                }
            });
        }

        // Collecter tous les blocs d'éditions (li) — Bedetheque affiche plusieurs éditions
        // On cherche le bloc qui contient notre ISBN recherché, sinon on prend le dernier (= plus récent)
        const allLis = Array.from(document.querySelectorAll('li'));
        const editionBlocks = [];
        let currentBlock = {};

        allLis.forEach(li => {
            const text = li.textContent.trim();
            if (/EAN\/ISBN/i.test(text)) {
                const match = text.match(/(\d[\d-]{9,16}\d)/);
                if (match) {
                    currentBlock.isbn = match[1].replace(/-/g, '');
                }
            }
            if (/^Editeur\s*:/i.test(text)) {
                currentBlock.editeur = text.replace(/^Editeur\s*:\s*/i, '').trim();
            }
            if (/^Planches\s*:/i.test(text)) {
                const match = text.match(/Planches\s*:\s*(\d+)/i);
                if (match) currentBlock.pages = parseInt(match[1]);
            }
            if (/Titre\s*:/i.test(text) && !data.titre) {
                data.titre = text.replace(/^Titre\s*:\s*/i, '').trim();
            }
            // Dépot légal = fin d'un bloc d'édition
            if (/p.t l.gal/i.test(text)) {
                const match = text.match(/(\d{2})\/(\d{4})/);
                if (match) currentBlock.annee = parseInt(match[2]);
                if (currentBlock.isbn) {
                    editionBlocks.push({...currentBlock});
                    currentBlock = {};
                }
            }
        });
        // Dernier bloc si non terminé
        if (currentBlock.isbn) {
            editionBlocks.push(currentBlock);
        }

        // Choisir la bonne édition : celle dont l'ISBN correspond à l'ISBN recherché, sinon la dernière
        if (editionBlocks.length > 0) {
            // Prendre la dernière (plus récente) par défaut
            let chosen = editionBlocks[editionBlocks.length - 1];
            // Chercher si une édition correspond à l'ISBN recherché
            if (searchedIsbn) {
                const cleanSearched = searchedIsbn.replace(/-/g, '');
                for (const block of editionBlocks) {
                    if (block.isbn === cleanSearched) {
                        chosen = block;
                        break;
                    }
                }
            }
            data.isbn = chosen.isbn || null;
            data.editeur = chosen.editeur || null;
            data.pages = chosen.pages || null;
            data.annee = chosen.annee || null;
        }

        // Résumé
        const descEl = document.querySelector('.album-resume, .description');
        if (descEl) data.resume = descEl.textContent.trim();

        // Image
        const imgEl = document.querySelector('img[src*="Couvertures"]');
        if (imgEl) data.image = imgEl.src;
        if (!data.image) {
            const metaImg = document.querySelector('meta[property="og:image"]');
            if (metaImg) data.image = metaImg.getAttribute('content');
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
