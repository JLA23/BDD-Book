<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookCoverService
{
    private ?HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Nettoie l'ISBN en retirant les tirets et espaces
     */
    private function cleanIsbn(?string $isbn): ?string
    {
        if (empty($isbn)) {
            return null;
        }
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Effectue une requête HTTP GET
     */
    private function httpGet(string $url): ?string
    {
        try {
            if ($this->httpClient === null) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'follow_location' => true,
                    ]
                ]);
                $response = @file_get_contents($url, false, $context);
                return $response !== false ? $response : null;
            } else {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 10,
                ]);
                return $response->getStatusCode() === 200 ? $response->getContent() : null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Recherche une couverture via Google Books API
     */
    public function getCoverUrlFromGoogleBooks(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";
        $response = $this->httpGet($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['items'][0]['volumeInfo']['imageLinks']['thumbnail'])) {
                $imageUrl = $data['items'][0]['volumeInfo']['imageLinks']['thumbnail'];
                // Améliorer la qualité de l'image
                $imageUrl = str_replace('zoom=1', 'zoom=0', $imageUrl);
                $imageUrl = str_replace('&edge=curl', '', $imageUrl);
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
                return $imageUrl;
            }
        }

        return null;
    }

    /**
     * Génère l'URL Open Library pour une couverture
     */
    public function getOpenLibraryCoverUrl(?string $isbn, string $size = 'L'): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }
        return "https://covers.openlibrary.org/b/isbn/{$isbn}-{$size}.jpg";
    }

    /**
     * Vérifie si Open Library a une couverture pour cet ISBN
     */
    public function checkOpenLibraryCover(?string $isbn): bool
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return false;
        }

        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
        $response = $this->httpGet($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data["ISBN:{$isbn}"])) {
                // Vérifier si une couverture existe
                return isset($data["ISBN:{$isbn}"]['cover']);
            }
        }
        
        return false;
    }

    /**
     * Recherche la meilleure image disponible en essayant plusieurs sources
     * 
     * @param string|null $isbn ISBN du livre
     * @return array{url: string|null, source: string|null}
     */
    public function findBestCover(?string $isbn): array
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return ['url' => null, 'source' => null];
        }

        // 1. Essayer Google Books (meilleure qualité généralement)
        $url = $this->getCoverUrlFromGoogleBooks($isbn);
        if ($url) {
            return ['url' => $url, 'source' => 'Google Books'];
        }

        // 2. Essayer Open Library avec vérification
        if ($this->checkOpenLibraryCover($isbn)) {
            $url = $this->getOpenLibraryCoverUrl($isbn, 'L');
            if ($url && $this->isValidImage($url)) {
                return ['url' => $url, 'source' => 'Open Library'];
            }
        }

        // 3. Essayer Amazon page produit directe (meilleure qualité)
        $url = $this->scrapeAmazonProductPage($isbn);
        if ($url && $this->isValidImageUrl($url)) {
            return ['url' => $url, 'source' => 'Amazon (produit)'];
        }

        // 4. Essayer Amazon recherche
        $url = $this->scrapeAmazonFr($isbn);
        if ($url && $this->isValidImageUrl($url)) {
            return ['url' => $url, 'source' => 'Amazon (recherche)'];
        }

        // 5. Générer URL directe vers image Open Library (sans vérification)
        // Open Library redirige vers l'image si elle existe
        $url = $this->getOpenLibraryCoverUrl($isbn, 'L');
        if ($url) {
            return ['url' => $url, 'source' => 'Open Library (direct)'];
        }

        return ['url' => null, 'source' => null];
    }

    /**
     * Scrape Amazon.fr pour trouver l'image de couverture
     */
    public function scrapeAmazonFr(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            // Rechercher sur Amazon.fr
            $searchUrl = "https://www.amazon.fr/s?k={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'follow_location' => true,
                    'max_redirects' => 3,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                               "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                               "Accept-Language: fr-FR,fr;q=0.9,en;q=0.8\r\n" .
                               "Accept-Encoding: gzip, deflate\r\n" .
                               "Connection: keep-alive\r\n" .
                               "Upgrade-Insecure-Requests: 1\r\n"
                ]
            ]);
            
            $html = @file_get_contents($searchUrl, false, $context);
            
            if ($html) {
                // Patterns multiples pour capturer les images Amazon
                $patterns = [
                    // Pattern 1: data-image-latency
                    '/data-image-latency="s-product-image"[^>]*src="([^"]+)"/',
                    // Pattern 2: class s-image
                    '/<img[^>]+class="[^"]*s-image[^"]*"[^>]+src="([^"]+)"/',
                    // Pattern 3: srcset avec la plus grande image
                    '/srcset="([^"]+)"\s+class="[^"]*s-image/',
                    // Pattern 4: data-old-hires
                    '/data-old-hires="([^"]+)"/',
                    // Pattern 5: image dans le JSON embarqué
                    '/"hiRes":"([^"]+)"/',
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $html, $matches)) {
                        $imageUrl = $matches[1];
                        
                        // Si c'est un srcset, prendre la première URL
                        if (strpos($imageUrl, ',') !== false) {
                            $parts = explode(',', $imageUrl);
                            $imageUrl = trim(explode(' ', $parts[0])[0]);
                        }
                        
                        // Nettoyer l'URL et améliorer la qualité
                        $imageUrl = html_entity_decode($imageUrl);
                        // Remplacer les dimensions pour avoir la meilleure qualité
                        $imageUrl = preg_replace('/\._[A-Z]+[0-9]+_\./', '.', $imageUrl);
                        $imageUrl = preg_replace('/\._[A-Z]{2}[0-9]+,?[0-9]*_\./', '.', $imageUrl);
                        
                        // Vérifier que c'est une vraie URL d'image
                        if ($this->isValidImageUrl($imageUrl)) {
                            return $imageUrl;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Scrape la page produit Amazon.fr directement avec l'ISBN
     */
    public function scrapeAmazonProductPage(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            // Accéder directement à la page produit via ISBN
            $productUrl = "https://www.amazon.fr/dp/{$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'follow_location' => true,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                               "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                               "Accept-Language: fr-FR,fr;q=0.9,en;q=0.8\r\n"
                ]
            ]);
            
            $html = @file_get_contents($productUrl, false, $context);
            
            if ($html) {
                // Chercher l'image principale du produit
                if (preg_match('/"hiRes":"([^"]+)"/', $html, $matches)) {
                    return str_replace('\\/', '/', $matches[1]);
                }
                
                if (preg_match('/"large":"([^"]+)"/', $html, $matches)) {
                    return str_replace('\\/', '/', $matches[1]);
                }
                
                // Image dans le tag img principal
                if (preg_match('/<img[^>]+id="landingImage"[^>]+src="([^"]+)"/', $html, $matches)) {
                    $imageUrl = $matches[1];
                    $imageUrl = preg_replace('/\._[A-Z]+[0-9]+_\./', '.', $imageUrl);
                    return $imageUrl;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Vérifie si une URL pointe vers une vraie image (pas un placeholder)
     */
    private function isValidImage(string $url): bool
    {
        $content = $this->httpGet($url);
        
        if ($content === null) {
            return false;
        }
        
        // Vérifier la taille - une vraie couverture fait généralement plus de 5KB
        if (strlen($content) < 5000) {
            return false;
        }
        
        return true;
    }

    /**
     * Télécharge l'image et retourne son contenu binaire
     */
    public function downloadImage(string $url): ?string
    {
        $content = $this->httpGet($url);
        
        // Vérifier que c'est une vraie image (pas un placeholder)
        if ($content !== null && strlen($content) > 1000) {
            return $content;
        }
        
        return null;
    }

    /**
     * Scrape une page web et récupère toutes les images de produit
     * 
     * @param string|null $pageUrl URL de la page à scraper
     * @return array Liste des URLs d'images trouvées
     */
    public function scrapeImagesFromUrl(?string $pageUrl): array
    {
        if (empty($pageUrl)) {
            return [];
        }

        $images = [];

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'follow_location' => true,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                               "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                               "Accept-Language: fr-FR,fr;q=0.9,en;q=0.8\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            
            $html = @file_get_contents($pageUrl, false, $context);
            
            if (!$html) {
                return [];
            }

            // Détecter le type de site et adapter le scraping
            if (strpos($pageUrl, 'amazon') !== false) {
                $images = $this->extractAmazonImages($html);
            } elseif (strpos($pageUrl, 'fnac') !== false) {
                $images = $this->extractFnacImages($html);
            } elseif (strpos($pageUrl, 'babelio') !== false) {
                $images = $this->extractBabelioImages($html);
            } else {
                // Extraction générique
                $images = $this->extractGenericImages($html, $pageUrl);
            }

        } catch (\Exception $e) {
            // Silently fail
        }

        // Filtrer les doublons et les images trop petites
        return array_values(array_unique($images));
    }

    /**
     * Extrait les images depuis une page Amazon
     */
    private function extractAmazonImages(string $html): array
    {
        $images = [];

        // Images haute résolution dans le JSON
        if (preg_match_all('/"hiRes"\s*:\s*"([^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = str_replace('\\/', '/', $url);
                if ($this->isProductImage($url)) {
                    $images[] = $url;
                }
            }
        }

        // Images "large" dans le JSON
        if (preg_match_all('/"large"\s*:\s*"([^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = str_replace('\\/', '/', $url);
                if ($this->isProductImage($url)) {
                    $images[] = $url;
                }
            }
        }

        // Image principale (landingImage)
        if (preg_match('/<img[^>]+id="landingImage"[^>]+(?:src|data-old-hires)="([^"]+)"/', $html, $matches)) {
            $url = $matches[1];
            // Améliorer la qualité
            $url = preg_replace('/\._[A-Z]{2}[0-9]+_\./', '.', $url);
            if ($this->isProductImage($url)) {
                $images[] = $url;
            }
        }

        // Images dans data-a-dynamic-image
        if (preg_match('/data-a-dynamic-image="([^"]+)"/', $html, $matches)) {
            $json = html_entity_decode($matches[1]);
            if (preg_match_all('/(https:[^"]+\.jpg)/', $json, $urlMatches)) {
                foreach ($urlMatches[1] as $url) {
                    $url = str_replace('\\/', '/', $url);
                    if ($this->isProductImage($url)) {
                        $images[] = $url;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Extrait les images depuis une page Fnac
     */
    private function extractFnacImages(string $html): array
    {
        $images = [];

        // Images produit Fnac
        if (preg_match_all('/src="(https:\/\/static\.fnac-static\.com\/multimedia\/Images\/[^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isProductImage($url)) {
                    $images[] = $url;
                }
            }
        }

        // Data-src pour lazy loading
        if (preg_match_all('/data-src="(https:\/\/static\.fnac-static\.com\/multimedia\/Images\/[^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isProductImage($url)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * Extrait les images depuis une page Babelio
     */
    private function extractBabelioImages(string $html): array
    {
        $images = [];

        if (preg_match_all('/src="(https:\/\/[^"]*babelio[^"]*couverture[^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $images[] = $url;
            }
        }

        return $images;
    }

    /**
     * Extraction générique d'images depuis une page HTML
     */
    private function extractGenericImages(string $html, string $baseUrl): array
    {
        $images = [];

        // Extraire toutes les images
        if (preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                // Convertir les URLs relatives en absolues
                if (strpos($url, 'http') !== 0) {
                    $parsedBase = parse_url($baseUrl);
                    $url = $parsedBase['scheme'] . '://' . $parsedBase['host'] . '/' . ltrim($url, '/');
                }
                
                if ($this->isProductImage($url)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    /**
     * Vérifie si une URL ressemble à une image de produit (pas un logo, icône, etc.)
     */
    private function isProductImage(string $url): bool
    {
        // Exclure les petites images, icônes, logos
        $excludePatterns = [
            '/sprite/',
            '/icon/',
            '/logo/',
            '/button/',
            '/pixel/',
            '/transparent/',
            '/blank/',
            '/1x1/',
            '/_SS40_',
            '/_SS50_',
            '/_SX38_',
            '/_SY75_',
            '/nav-sprite/',
            '/loading/',
        ];

        foreach ($excludePatterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }

        // Doit être une image
        if (!preg_match('/\.(jpg|jpeg|png|webp|gif)/i', $url)) {
            return false;
        }

        return true;
    }

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

    /**
     * Scrape BDGuest pour trouver l'image de couverture
     */
    public function scrapeBDGuest(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            $searchUrl = "https://www.bdgest.com/search.php?RechSerie={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ]
            ]);
            
            $html = @file_get_contents($searchUrl, false, $context);
            
            if ($html) {
                // Pattern pour les images de couverture BDGuest
                if (preg_match('/<img[^>]+src="(https?:\/\/[^"]*bdgest[^"]*\/Couvertures[^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
                
                // Alternative: chercher dans les vignettes
                if (preg_match('/<img[^>]+class="[^"]*couv[^"]*"[^>]+src="([^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Scrape Fnac pour trouver l'image de couverture
     */
    public function scrapeFnac(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            $searchUrl = "https://www.fnac.com/SearchResult/ResultList.aspx?Search={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                               "Accept: text/html,application/xhtml+xml\r\n"
                ]
            ]);
            
            $html = @file_get_contents($searchUrl, false, $context);
            
            if ($html) {
                // Pattern pour les images Fnac
                if (preg_match('/<img[^>]+data-src="(https?:\/\/[^"]*static\.fnac-static\.com[^"]*\/multimedia[^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
                
                // Alternative: src direct
                if (preg_match('/<img[^>]+src="(https?:\/\/[^"]*static\.fnac-static\.com[^"]*\/multimedia[^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Scrape Decitre pour trouver l'image de couverture
     */
    public function scrapeDecitre(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            $searchUrl = "https://www.decitre.fr/rechercher/result?q={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ]
            ]);
            
            $html = @file_get_contents($searchUrl, false, $context);
            
            if ($html) {
                // Pattern pour les images Decitre
                if (preg_match('/<img[^>]+data-src="(https?:\/\/[^"]*products\.decitre\.fr[^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
                
                if (preg_match('/<img[^>]+src="(https?:\/\/[^"]*products\.decitre\.fr[^"]+)"/', $html, $matches)) {
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Scrape ExcaliburComics pour trouver l'image de couverture
     */
    public function scrapeExcaliburComics(?string $isbn): ?string
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return null;
        }

        try {
            $searchUrl = "https://www.excaliburcomics.fr/search?q={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ]
            ]);
            
            $html = @file_get_contents($searchUrl, false, $context);
            
            if ($html) {
                // Pattern pour les images ExcaliburComics
                if (preg_match('/<img[^>]+class="[^"]*product[^"]*"[^>]+src="([^"]+)"/', $html, $matches)) {
                    $imageUrl = $matches[1];
                    if (!str_starts_with($imageUrl, 'http')) {
                        $imageUrl = 'https://www.excaliburcomics.fr' . $imageUrl;
                    }
                    return $imageUrl;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Recherche la meilleure image en essayant tous les sites disponibles via Puppeteer
     * 
     * @param string|null $isbn
     * @return array Liste de toutes les images trouvées avec leur source
     */
    public function findAllCovers(?string $isbn): array
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return [];
        }

        // Utiliser le service Puppeteer pour tout scraper en une fois
        if (!($_ENV['PUPPETEER_SERVICE_URL'] ?? false)) {
            return [];
        }

        try {
            $apiKey = $_ENV['PUPPETEER_API_KEY'] ?? 'votre-cle-secrete';
            $serviceUrl = $_ENV['PUPPETEER_SERVICE_URL'] ?? 'http://localhost:3000';
            
            $url = "{$serviceUrl}/scrape/all?isbn={$isbn}";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 120,
                    'ignore_errors' => true,
                    'header' => "X-API-Key: {$apiKey}\r\n"
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("Puppeteer service unreachable at {$serviceUrl}");
                return [];
            }
            
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success'] && !empty($data['images'])) {
                return $data['images'];
            }
        } catch (\Exception $e) {
            error_log("Puppeteer error: " . $e->getMessage());
        }
        
        return [];
    }
}
