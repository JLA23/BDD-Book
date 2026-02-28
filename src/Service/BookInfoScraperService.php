<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookInfoScraperService
{
    private ?HttpClientInterface $httpClient;
    private string $puppeteerUrl;
    private string $puppeteerApiKey;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient;
        $this->puppeteerUrl = $_ENV['PUPPETEER_SERVICE_URL'] ?? 'http://localhost:3000';
        $this->puppeteerApiKey = $_ENV['PUPPETEER_API_KEY'] ?? 'votre-cle-secrete';
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
    private function httpGet(string $url, array $headers = []): ?string
    {
        try {
            if ($this->httpClient !== null) {
                $options = ['timeout' => 15];
                if (!empty($headers)) {
                    $options['headers'] = $headers;
                }
                $response = $this->httpClient->request('GET', $url, $options);
                return $response->getStatusCode() === 200 ? $response->getContent() : null;
            }

            $httpHeaders = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n";
            foreach ($headers as $key => $value) {
                $httpHeaders .= "{$key}: {$value}\r\n";
            }
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'follow_location' => true,
                    'header' => $httpHeaders,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            return $response !== false ? $response : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Appelle le service Puppeteer Node.js
     */
    private function callPuppeteer(string $endpoint, array $params = []): ?array
    {
        try {
            $queryString = http_build_query($params);
            $url = "{$this->puppeteerUrl}{$endpoint}?{$queryString}";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 120,
                    'ignore_errors' => true,
                    'header' => "X-API-Key: {$this->puppeteerApiKey}\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                error_log("Puppeteer service unreachable at {$this->puppeteerUrl}");
                return null;
            }

            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                return $data['data'] ?? $data;
            }

            return null;
        } catch (\Exception $e) {
            error_log("Puppeteer error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Recherche les informations d'un livre par ISBN sur toutes les sources,
     * puis fusionne tous les résultats en un seul résultat optimal.
     * Retourne null si aucun résultat.
     */
    public function searchByIsbn(string $isbn): ?array
    {
        $results = $this->searchByIsbnAll($isbn);
        if (empty($results)) {
            return null;
        }
        return $this->mergeResults($results);
    }

    /**
     * Recherche ISBN sur toutes les sources — retourne TOUS les résultats bruts
     */
    public function searchByIsbnAll(string $isbn): array
    {
        $isbn = $this->cleanIsbn($isbn);
        if (empty($isbn)) {
            return [];
        }

        $results = [];

        $data = $this->fetchGoogleBooks("isbn:{$isbn}");
        if ($data) {
            $results[] = $data;
        }

        $data = $this->fetchOpenLibrary($isbn);
        if ($data) {
            $results[] = $data;
        }

        $puppeteerResults = $this->callPuppeteerSearch($isbn);
        if (!empty($puppeteerResults)) {
            $results = array_merge($results, $puppeteerResults);
        }

        return $results;
    }

    /**
     * Fusionne plusieurs résultats en un seul résultat optimal.
     * Pour chaque champ, prend la première valeur non-vide trouvée.
     * Pour les auteurs, fusionne et déduplique.
     * Pour l'image, préfère les images HD (Amazon).
     * Pour les sources, concatène toutes les sources.
     */
    public function mergeResults(array $results): array
    {
        $merged = [
            'titre' => null,
            'auteurs' => [],
            'editeur' => null,
            'isbn' => null,
            'annee' => null,
            'pages' => null,
            'resume' => null,
            'image' => null,
            'source' => null,
            'sourceUrl' => null,
            'categories' => [],
        ];

        $sources = [];
        $bestImageScore = 0;

        foreach ($results as $r) {
            // Titre : prendre le plus long (généralement le plus complet)
            if (!empty($r['titre']) && (empty($merged['titre']) || mb_strlen($r['titre']) > mb_strlen($merged['titre']))) {
                $merged['titre'] = $r['titre'];
            }

            // ISBN
            if (empty($merged['isbn']) && !empty($r['isbn'])) {
                $merged['isbn'] = $r['isbn'];
            }

            // Année
            if (empty($merged['annee']) && !empty($r['annee'])) {
                $merged['annee'] = $r['annee'];
            }

            // Pages
            if (empty($merged['pages']) && !empty($r['pages'])) {
                $merged['pages'] = $r['pages'];
            }

            // Éditeur
            if (empty($merged['editeur']) && !empty($r['editeur'])) {
                $merged['editeur'] = $r['editeur'];
            }

            // Résumé : prendre le plus long
            if (!empty($r['resume']) && (empty($merged['resume']) || mb_strlen($r['resume']) > mb_strlen($merged['resume']))) {
                $merged['resume'] = $r['resume'];
            }

            // Auteurs : fusionner et dédupliquer
            if (!empty($r['auteurs']) && is_array($r['auteurs'])) {
                foreach ($r['auteurs'] as $auteur) {
                    $auteurNorm = mb_strtolower(trim($auteur));
                    $found = false;
                    foreach ($merged['auteurs'] as $existing) {
                        if (mb_strtolower(trim($existing)) === $auteurNorm) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && !empty(trim($auteur))) {
                        $merged['auteurs'][] = trim($auteur);
                    }
                }
            }

            // Image : scorer par qualité (Amazon HD > autres)
            if (!empty($r['image'])) {
                $score = 1;
                if (strpos($r['image'], 'media-amazon.com') !== false) {
                    $score = strpos($r['image'], 'SL1500') !== false ? 10 : 5;
                } elseif (strpos($r['image'], 'bedetheque.com') !== false) {
                    $score = 3;
                } elseif (strpos($r['image'], 'books.google') !== false) {
                    $score = 2;
                }
                if ($score > $bestImageScore) {
                    $bestImageScore = $score;
                    $merged['image'] = $r['image'];
                }
            }

            // SourceUrl
            if (empty($merged['sourceUrl']) && !empty($r['sourceUrl'])) {
                $merged['sourceUrl'] = $r['sourceUrl'];
            }

            // Catégories
            if (!empty($r['categories']) && is_array($r['categories'])) {
                $merged['categories'] = array_unique(array_merge($merged['categories'], $r['categories']));
            }

            // Collecter les sources
            if (!empty($r['source'])) {
                $sources[] = $r['source'];
            }
        }

        $merged['source'] = implode(', ', array_unique($sources));

        return $merged;
    }

    /**
     * Appelle /scrape/search et retourne tous les résultats (allResults)
     */
    private function callPuppeteerSearch(string $isbn): array
    {
        try {
            $queryString = http_build_query(['isbn' => $isbn]);
            $url = "{$this->puppeteerUrl}/scrape/search?{$queryString}";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 180,
                    'ignore_errors' => true,
                    'header' => "X-API-Key: {$this->puppeteerApiKey}\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return [];
            }

            $json = json_decode($response, true);
            if (!$json || !isset($json['success']) || !$json['success']) {
                return [];
            }

            // Le endpoint retourne allResults avec tous les résultats des sites
            if (isset($json['allResults']) && is_array($json['allResults'])) {
                return $json['allResults'];
            }

            // Fallback : un seul résultat dans data
            if (isset($json['data']) && !empty($json['data']['titre'])) {
                return [$json['data']];
            }

            return [];
        } catch (\Exception $e) {
            error_log("Puppeteer search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Recherche les informations d'un livre par titre (Google Books API)
     */
    public function searchByTitle(string $title, ?string $author = null): array
    {
        $query = $title;
        if ($author) {
            $query .= '+inauthor:' . $author;
        }

        $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query) . "&maxResults=10&langRestrict=fr";
        $response = $this->httpGet($url);

        if (!$response) {
            return [];
        }

        $json = json_decode($response, true);
        if (!isset($json['items'])) {
            return [];
        }

        $results = [];
        foreach ($json['items'] as $item) {
            $info = $this->parseGoogleBooksItem($item);
            if ($info) {
                $results[] = $info;
            }
        }

        return $results;
    }

    /**
     * Scrape les informations d'un livre depuis une URL via Puppeteer
     * Sites supportés : Amazon, Fnac, Babelio, Bedetheque
     */
    public function scrapeFromUrl(string $url): ?array
    {
        return $this->callPuppeteer('/scrape/info', ['url' => $url]);
    }

    /**
     * Retourne la liste des sites supportés pour le scraping par URL
     */
    public function getSupportedSites(): array
    {
        return [
            ['name' => 'Amazon.fr', 'domain' => 'amazon.fr', 'icon' => 'fab fa-amazon'],
            ['name' => 'Fnac', 'domain' => 'fnac.com', 'icon' => 'fas fa-store'],
            ['name' => 'Babelio', 'domain' => 'babelio.com', 'icon' => 'fas fa-book-reader'],
            ['name' => 'Bedetheque', 'domain' => 'bedetheque.com', 'icon' => 'fas fa-book-open'],
        ];
    }

    /**
     * Valide qu'une URL est d'un site supporté
     */
    public function isUrlSupported(string $url): bool
    {
        $supportedDomains = ['amazon.fr', 'amazon.com', 'fnac.com', 'babelio.com', 'bedetheque.com', 'bdgest.com'];
        foreach ($supportedDomains as $domain) {
            if (stripos($url, $domain) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Détecte le nom du site depuis une URL
     */
    public function getSiteNameFromUrl(string $url): string
    {
        if (stripos($url, 'amazon') !== false) return 'Amazon';
        if (stripos($url, 'fnac') !== false) return 'Fnac';
        if (stripos($url, 'babelio') !== false) return 'Babelio';
        if (stripos($url, 'bedetheque') !== false || stripos($url, 'bdgest') !== false) return 'Bedetheque';
        return 'Inconnu';
    }

    // ===================== GOOGLE BOOKS =====================

    private function fetchGoogleBooks(string $query): ?array
    {
        $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query) . "&maxResults=1";
        $response = $this->httpGet($url);

        if (!$response) {
            return null;
        }

        $json = json_decode($response, true);
        if (!isset($json['items'][0])) {
            return null;
        }

        return $this->parseGoogleBooksItem($json['items'][0]);
    }

    private function parseGoogleBooksItem(array $item): ?array
    {
        $vol = $item['volumeInfo'] ?? [];
        if (empty($vol)) {
            return null;
        }

        $isbn13 = null;
        $isbn10 = null;
        foreach (($vol['industryIdentifiers'] ?? []) as $id) {
            if ($id['type'] === 'ISBN_13') {
                $isbn13 = $id['identifier'];
            }
            if ($id['type'] === 'ISBN_10') {
                $isbn10 = $id['identifier'];
            }
        }

        $imageUrl = null;
        if (isset($vol['imageLinks'])) {
            $imageUrl = $vol['imageLinks']['extraLarge']
                ?? $vol['imageLinks']['large']
                ?? $vol['imageLinks']['medium']
                ?? $vol['imageLinks']['thumbnail']
                ?? null;
            if ($imageUrl) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
                $imageUrl = str_replace('zoom=1', 'zoom=0', $imageUrl);
                $imageUrl = str_replace('&edge=curl', '', $imageUrl);
            }
        }

        return [
            'titre' => $vol['title'] ?? null,
            'auteurs' => $vol['authors'] ?? [],
            'editeur' => $vol['publisher'] ?? null,
            'isbn' => $isbn13 ?? $isbn10 ?? null,
            'annee' => isset($vol['publishedDate']) ? (int) substr($vol['publishedDate'], 0, 4) : null,
            'pages' => $vol['pageCount'] ?? null,
            'resume' => $vol['description'] ?? null,
            'image' => $imageUrl,
            'categories' => $vol['categories'] ?? [],
            'source' => 'Google Books',
        ];
    }

    // ===================== OPEN LIBRARY =====================

    private function fetchOpenLibrary(string $isbn): ?array
    {
        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
        $response = $this->httpGet($url);

        if (!$response) {
            return null;
        }

        $json = json_decode($response, true);
        $key = "ISBN:{$isbn}";
        if (!isset($json[$key])) {
            return null;
        }

        $data = $json[$key];
        $auteurs = [];
        foreach (($data['authors'] ?? []) as $author) {
            $auteurs[] = $author['name'] ?? '';
        }

        $editeurs = [];
        foreach (($data['publishers'] ?? []) as $pub) {
            $editeurs[] = $pub['name'] ?? '';
        }

        return [
            'titre' => $data['title'] ?? null,
            'auteurs' => $auteurs,
            'editeur' => !empty($editeurs) ? $editeurs[0] : null,
            'isbn' => $isbn,
            'annee' => isset($data['publish_date']) ? (int) preg_replace('/\D/', '', substr($data['publish_date'], -4)) : null,
            'pages' => $data['number_of_pages'] ?? null,
            'resume' => null,
            'image' => isset($data['cover']) ? ($data['cover']['large'] ?? $data['cover']['medium'] ?? null) : null,
            'categories' => [],
            'source' => 'Open Library',
        ];
    }
}
