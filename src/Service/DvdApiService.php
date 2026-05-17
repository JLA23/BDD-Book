<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client API DVDFr (https://www.dvdfr.com/api/documentation.php).
 * Recherche : pas de clé requise. Clé optionnelle pour appels signés (fiche avancée / quotas PRO).
 */
class DvdApiService
{
    private const BASE_URL = 'https://www.dvdfr.com/api';
    private const USER_AGENT = 'BDD-Books-DEV/1.0 (+https://github.com)';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $apiKey = null,
    ) {
    }

    /**
     * La recherche par titre fonctionne sans clé API.
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
        $title = $this->prepareSearchTitle($query);
        if ($title === '') {
            return [];
        }

        $params = ['title' => $title];
        $produit = $this->mapFormatToProduit($format);
        if ($produit !== null) {
            $params['produit'] = $produit;
        }

        $xml = $this->requestXml(self::BASE_URL . '/search.php', $params);
        if ($xml === null || $xml->getName() === 'errors') {
            return [];
        }

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

    /**
     * Recherche par code-barres EAN-13 (paramètre API gencode).
     *
     * @return list<array{id: string, titre: string, format: string, annee: ?int, editeur: string, cover: string, type: string, edition: string}>
     */
    public function searchByGencode(string $gencode, ?string $format = null, int $limit = 20): array
    {
        $gencode = $this->normalizeGencode($gencode);
        if ($gencode === '') {
            return [];
        }

        $params = ['gencode' => $gencode];
        $produit = $this->mapFormatToProduit($format);
        if ($produit !== null) {
            $params['produit'] = $produit;
        }

        $xml = $this->requestXml(self::BASE_URL . '/search.php', $params);
        if ($xml === null || $xml->getName() === 'errors') {
            return [];
        }

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

    /**
     * @return array{id: string, titre: string, format: string, annee: ?int, editeur: string, description: string, cover: string, type: string, edition: string, ean: string}|null
     */
    public function getDetails(string $dvdId): ?array
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

        $xml = $this->requestXml(self::BASE_URL . '/dvd.php', $params);
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

    private function requestXml(string $url, array $query): ?\SimpleXMLElement
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $query,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
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
        if (str_contains($haystack, 'BRD') || str_contains($haystack, 'BLU') || str_contains($haystack, 'HDDVD')) {
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
