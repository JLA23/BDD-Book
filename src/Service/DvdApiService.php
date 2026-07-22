<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client DVDFr : API XML (si DVDFR_USE_API=1) ou scraping HTML du site.
 * Recherche : pas de clé requise. Clé optionnelle pour appels API signés (fiche / quotas PRO).
 */
class DvdApiService
{
    private const API_BASE_URL = 'https://www.dvdfr.com/api';
    private const SITE_BASE_URL = 'https://www.dvdfr.com';
    private const USER_AGENT_API = 'BDD-Books-DEV/1.0 (+https://github.com)';
    private const USER_AGENT_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /** Codes support recherche avancée DVDFr (recherche_avancee.php → code_support). */
    private const FORMAT_CODE_SUPPORT = [
        'dvd' => '336',
        'bluray' => '330',
        'bluray4k' => '333',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $apiKey = null,
        private bool $useApi = false,
    ) {
    }

    public function usesApi(): bool
    {
        return $this->useApi;
    }

    /**
     * La recherche fonctionne sans clé API (API ou scraping).
     */
    public function isConfigured(): bool
    {
        return true;
    }

    public function hasSignedApiKey(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    public function search(string $query, ?string $format = null, int $limit = 20): array
    {
        return $this->useApi
            ? $this->searchViaApi($query, $format, $limit)
            : $this->searchViaScraping($query, $format, $limit);
    }

    /**
     * Recherche par code-barres EAN-13.
     *
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    public function searchByGencode(string $gencode, ?string $format = null, int $limit = 20): array
    {
        $gencode = $this->normalizeGencode($gencode);
        if ($gencode === '') {
            return [];
        }

        return $this->useApi
            ? $this->searchByGencodeViaApi($gencode, $format, $limit)
            : $this->searchViaScraping($gencode, $format, $limit);
    }

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, description: string, cover: string, type: string, edition: string, ean: string}|null
     */
    public function getDetails(string $dvdId): ?array
    {
        return $this->useApi
            ? $this->getDetailsViaApi($dvdId)
            : $this->getDetailsViaScraping($dvdId);
    }

    // -------------------------------------------------------------------------
    // API XML
    // -------------------------------------------------------------------------

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    private function searchViaApi(string $query, ?string $format, int $limit): array
    {
        $title = $this->prepareSearchTitle($query);
        if ($title === '') {
            return [];
        }

        $params = ['title' => $title];
        $produit = $this->mapFormatToProduit($format);
        if ($produit !== null) {
            $params['produit'] = $produit;
        }

        $xml = $this->requestXml(self::API_BASE_URL . '/search.php', $params);
        if ($xml === null || $xml->getName() === 'errors') {
            return [];
        }

        return $this->collectApiSearchResults($xml, $format, $limit);
    }

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    private function searchByGencodeViaApi(string $gencode, ?string $format, int $limit): array
    {
        $params = ['gencode' => $gencode];
        $produit = $this->mapFormatToProduit($format);
        if ($produit !== null) {
            $params['produit'] = $produit;
        }

        $xml = $this->requestXml(self::API_BASE_URL . '/search.php', $params);
        if ($xml === null || $xml->getName() === 'errors') {
            return [];
        }

        return $this->collectApiSearchResults($xml, $format, $limit);
    }

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, description: string, cover: string, type: string, edition: string, ean: string}|null
     */
    private function getDetailsViaApi(string $dvdId): ?array
    {
        $dvdId = trim($dvdId);
        if ($dvdId === '' || !ctype_digit($dvdId)) {
            return null;
        }

        $params = ['id' => $dvdId];
        if ($this->hasSignedApiKey()) {
            $params['ts'] = (string) time();
            $params['key'] = $this->apiKey;
        }

        $xml = $this->requestXml(self::API_BASE_URL . '/dvd.php', $params);
        if ($xml === null || $xml->getName() === 'errors') {
            return null;
        }

        $titre = $this->xmlText($xml->titres->fr ?? $xml->titres->vo ?? null);
        if ($titre === '') {
            return null;
        }

        $media = $this->xmlText($xml->media ?? null);

        return [
            'id' => $this->xmlText($xml->id ?? $dvdId),
            'titre' => $titre,
            'format' => $this->normalizeFormat($media, $this->xmlText($xml->edition ?? null)),
            'annee' => $this->parseYear($this->xmlText($xml->annee ?? null)),
            'editeur' => $this->xmlText($xml->editeur ?? null),
            'description' => $this->xmlText($xml->synopsis ?? null),
            'cover' => $this->xmlText($xml->cover ?? null),
            'type' => $this->guessType($titre, $this->xmlText($xml->edition ?? null)),
            'edition' => $this->xmlText($xml->edition ?? null),
            'ean' => $this->xmlText($xml->ean ?? null),
        ];
    }

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    private function collectApiSearchResults(\SimpleXMLElement $xml, ?string $format, int $limit): array
    {
        $results = [];
        foreach ($xml->dvd ?? [] as $dvdNode) {
            $item = $this->mapSearchResultNode($dvdNode);
            if ($format !== null && !$this->matchesFormatFilter($item['format'], $format)) {
                continue;
            }
            $results[] = $item;
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function requestXml(string $url, array $query): ?\SimpleXMLElement
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $query,
                'headers' => [
                    'User-Agent' => self::USER_AGENT_API,
                    'Accept' => 'application/xml, text/xml, */*',
                ],
                'timeout' => 15,
            ]);

            $body = $response->getContent();
            if ($body === '' || str_starts_with(trim($body), '<html')) {
                return null;
            }

            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $xml instanceof \SimpleXMLElement ? $xml : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}
     */
    private function mapSearchResultNode(\SimpleXMLElement $node): array
    {
        $titre = $this->xmlText($node->titres->fr ?? $node->titres->vo ?? null);
        $edition = $this->xmlText($node->edition ?? null);
        $media = $this->xmlText($node->media ?? null);

        return [
            'id' => $this->xmlText($node->id ?? ''),
            'titre' => $titre,
            'format' => $this->normalizeFormat($media, $edition),
            'annee' => $this->parseYear($this->xmlText($node->annee ?? null)),
            'editeur' => $this->xmlText($node->editeur ?? null),
            'cover' => $this->xmlText($node->cover ?? null),
            'type' => $this->guessType($titre, $edition),
            'edition' => $edition,
        ];
    }

    // -------------------------------------------------------------------------
    // Scraping HTML (site DVDFr / Epagine)
    // -------------------------------------------------------------------------

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    private function searchViaScraping(string $query, ?string $format, int $limit): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($query === '') {
            return [];
        }

        // EAN / gencode : envoyer tel quel. Titre : retirer les articles comme l'API.
        $searchTerm = preg_match('/^\d{12,13}$/', preg_replace('/\D+/', '', $query) ?? '')
            ? $this->normalizeGencode($query)
            : $this->prepareSearchTitle($query);

        if ($searchTerm === '') {
            return [];
        }

        $params = [
            'mots_recherche' => $searchTerm,
            'base' => 'dvd',
        ];
        $codeSupport = self::FORMAT_CODE_SUPPORT[$format ?? ''] ?? null;
        if ($codeSupport !== null) {
            $params['code_support'] = $codeSupport;
        }

        $html = $this->requestHtml(self::SITE_BASE_URL . '/listeliv.php', $params);
        if ($html === null) {
            return [];
        }

        return $this->parseSearchResultsHtml($html, $format, $limit);
    }

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, description: string, cover: string, type: string, edition: string, ean: string}|null
     */
    private function getDetailsViaScraping(string $dvdId): ?array
    {
        $dvdId = trim($dvdId);
        if ($dvdId === '' || !ctype_digit($dvdId)) {
            return null;
        }

        $html = $this->requestHtml(self::SITE_BASE_URL . '/dvd/' . $dvdId . '/', []);
        if ($html === null) {
            return null;
        }

        return $this->parseProductHtml($html, $dvdId);
    }

    /**
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    private function parseSearchResultsHtml(string $html, ?string $format, int $limit): array
    {
        $results = [];
        if (!preg_match_all(
            '/class="livre_titre"[^>]*>\s*<a\s+href="(\/dvd\/(\d+)[^"]*)"[^>]*>(.*?)<\/a>/isu',
            $html,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        foreach ($matches as $match) {
            $href = html_entity_decode($match[1][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ean = $match[2][0];
            $titre = trim(html_entity_decode(strip_tags($match[3][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($titre === '' || $ean === '') {
                continue;
            }

            $offset = $match[0][1];
            $windowStart = max(0, $offset - 2500);
            $window = substr($html, $windowStart, ($offset - $windowStart) + 3500);

            $cover = '';
            if (preg_match('/src="(https:\/\/images\.epagine\.fr\/[^"]+)"/i', $window, $imgMatch)) {
                $cover = html_entity_decode($imgMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $editeur = '';
            if (preg_match('/itemprop="publisher"[^>]*>(.*?)<\/span>/isu', $window, $edMatch)) {
                $editeur = trim(html_entity_decode(strip_tags($edMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $formatLabel = '';
            if (preg_match('/>\s*(4K Ultra HD|Blu-ray 3D|Blu-ray|DVD|UMD|HD DVD|VHS)\s*</iu', $window, $fmtMatch)) {
                $formatLabel = $fmtMatch[1];
            }
            $normalizedFormat = $this->normalizeFormat($formatLabel, $titre);

            $annee = null;
            if (preg_match('/class="MiseEnLigne"[^>]*>\s*[^<]*?(\d{4})\s*</iu', $window, $yearMatch)) {
                $annee = (int) $yearMatch[1];
            }

            if ($format !== null && !$this->matchesFormatFilter($normalizedFormat, $format)) {
                continue;
            }

            $results[] = [
                'id' => $ean,
                'titre' => $titre,
                'format' => $normalizedFormat,
                'annee' => $annee,
                'editeur' => $editeur,
                'cover' => $cover,
                'type' => $this->guessType($titre, ''),
                'edition' => '',
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, description: string, cover: string, type: string, edition: string, ean: string}|null
     */
    private function parseProductHtml(string $html, string $fallbackId): ?array
    {
        $titre = '';
        if (preg_match('/<h1[^>]*class="[^"]*titre[^"]*"[^>]*>(.*?)<\/h1>/isu', $html, $m)) {
            $titre = trim(html_entity_decode(strip_tags(preg_replace('/<span[^>]*>.*?<\/span>/isu', '', $m[1]) ?? $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($titre === '' && preg_match('/og:title"\s+content="([^"]+)"/i', $html, $m)) {
            $titre = trim(explode(' - ', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))[0]);
        }
        if ($titre === '') {
            return null;
        }

        $annee = null;
        if (preg_match('/class="commentLangue">\((\d{4})\)<\/span>/i', $html, $m)) {
            $annee = (int) $m[1];
        }

        $editeur = '';
        if (preg_match('/metaProductLight-editeur[^>]*>.*?<a[^>]*>(.*?)<\/a>/isu', $html, $m)) {
            $editeur = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif (preg_match('/itemprop="publisher"[^>]*>(.*?)<\//isu', $html, $m)) {
            $editeur = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $formatLabel = '';
        if (preg_match('/<ul class="metaProductLight">.*?<(?:li)[^>]*>\s*(4K Ultra HD|Blu-ray 3D|Blu-ray|DVD|UMD|HD DVD|VHS)\s*</isu', $html, $m)) {
            $formatLabel = $m[1];
        } elseif (preg_match('/og:title"\s+content="([^"]+)"/i', $html, $m)) {
            $formatLabel = $m[1];
        }

        $cover = '';
        if (preg_match('/og:image"\s+content="([^"]+)"/i', $html, $m)) {
            $cover = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (preg_match('/src="(https:\/\/images\.epagine\.fr\/[^"]+_1\.jpg)"/i', $html, $m)) {
            $cover = $m[1];
        }

        $description = $this->extractProductDescription($html);

        $ean = $fallbackId;
        if (preg_match('/gencodcoupcoeur="(\d{13})"/i', $html, $m)) {
            $ean = $m[1];
        } elseif (preg_match('/\bEAN\b[^0-9]{0,40}(\d{13})/iu', $html, $m)) {
            $ean = $m[1];
        }

        $edition = '';
        if (preg_match('/<h2[^>]*class="[^"]*titre_vo[^"]*"[^>]*>(.*?)<\/h2>/isu', $html, $m)) {
            $edition = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return [
            'id' => $ean,
            'titre' => $titre,
            'format' => $this->normalizeFormat($formatLabel, $edition),
            'annee' => $annee,
            'editeur' => $editeur,
            'description' => $description,
            'cover' => $cover,
            'type' => $this->guessType($titre, $edition),
            'edition' => $edition,
            'ean' => $ean,
        ];
    }

    /**
     * Assemble synopsis + « Contenu et bonus » depuis les blocs HTML produit.
     */
    private function extractProductDescription(string $html): string
    {
        $parts = [];

        $synopsis = $this->extractTitreDescriptionBlock($html, 'Synopsis');
        if ($synopsis !== '') {
            $parts[] = $synopsis;
        }

        $contenu = $this->extractTitreDescriptionBlock($html, 'Contenu et bonus');
        if ($contenu === '') {
            $contenu = $this->extractTitreDescriptionBlock($html, 'Contenu');
        }
        if ($contenu !== '') {
            $parts[] = "Contenu et bonus :\n" . $contenu;
        }

        if ($parts !== []) {
            return implode("\n\n", $parts);
        }

        // Fallback meta (souvent tronquée)
        if (preg_match('/og:description"\s+content="([^"]+)"/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    /**
     * Extrait le texte d'un bloc h2.titreDescription + p.description.
     */
    private function extractTitreDescriptionBlock(string $html, string $heading): string
    {
        $headingQuoted = preg_quote($heading, '/');
        if (!preg_match(
            '/<h2[^>]*class="[^"]*titreDescription[^"]*"[^>]*>\s*' . $headingQuoted . '\s*<\/h2>.*?<p[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/p>/isu',
            $html,
            $m
        )) {
            return '';
        }

        return $this->htmlFragmentToText($m[1]);
    }

    private function htmlFragmentToText(string $html): string
    {
        // Boutons UI « Lire la suite » / « Fermer » inclus dans le <p> synopsis
        $html = preg_replace('/<button\b[^>]*>.*?<\/button>/isu', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/iu', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p\s*>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]*/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{2,}/u", "\n", $text) ?? $text;
        // Filet de sécurité si le markup change
        $text = preg_replace('/\s*(?:Lire la suite|Fermer)\s*/iu', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;

        return trim($text);
    }

    private function requestHtml(string $url, array $query): ?string
    {
        try {
            $options = [
                'headers' => [
                    'User-Agent' => self::USER_AGENT_BROWSER,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ],
                'timeout' => 20,
                'max_redirects' => 5,
            ];
            if ($query !== []) {
                $options['query'] = $query;
            }

            $response = $this->httpClient->request('GET', $url, $options);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 400) {
                return null;
            }

            $body = $response->getContent();

            return $body !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers partagés
    // -------------------------------------------------------------------------

    private function normalizeGencode(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        // UPC-A (12 chiffres) → EAN-13 avec zéro en tête
        if (strlen($digits) === 12) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * DVDFr stocke l'article à part : le retirer du titre de recherche.
     */
    private function prepareSearchTitle(string $query): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($title === '') {
            return '';
        }

        $pattern = '/^(?:le|la|les|l\'|un|une|des|du|de la|de l\'|de|d\'|au|aux)\s+/iu';
        while (preg_match($pattern, $title)) {
            $title = preg_replace($pattern, '', $title, 1) ?? $title;
            $title = trim($title);
        }

        return $title;
    }

    private function mapFormatToProduit(?string $format): ?string
    {
        return match ($format) {
            'dvd' => 'DVD',
            'bluray' => 'BRD',
            'bluray4k' => 'HD',
            default => null,
        };
    }

    private function matchesFormatFilter(string $normalized, ?string $requested): bool
    {
        if ($requested === null || $requested === '') {
            return true;
        }

        return $normalized === $requested;
    }

    private function normalizeFormat(string $media, string $edition = ''): string
    {
        $haystack = strtoupper($media . ' ' . $edition);
        if (str_contains($haystack, 'UHD') || str_contains($haystack, '4K') || str_contains($haystack, 'ULTRA HD')) {
            return 'bluray4k';
        }
        if (str_contains($haystack, 'BRD') || str_contains($haystack, 'BLU') || str_contains($haystack, 'HDDVD') || str_contains($haystack, 'HD DVD')) {
            return 'bluray';
        }

        return 'dvd';
    }

    private function guessType(string $titre, string $edition = ''): string
    {
        $haystack = strtolower($titre . ' ' . $edition);
        if (preg_match('/\b(saison|season|série|serie|integrale|intégrale|coffret|collection)\b/u', $haystack)) {
            return 'serie';
        }

        return 'film';
    }

    private function parseYear(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $year = (int) $value;

        return $year > 0 ? $year : null;
    }

    private function xmlText(mixed $node): string
    {
        if ($node === null) {
            return '';
        }

        return trim(html_entity_decode((string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
