<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MusiqueApiService
{
    private HttpClientInterface $httpClient;
    private ?string $consumerKey;
    private ?string $consumerSecret;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->consumerKey = $_ENV['DISCOGS_CONSUMER_KEY'] ?? null;
        $this->consumerSecret = $_ENV['DISCOGS_CONSUMER_SECRET'] ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->consumerKey) && !empty($this->consumerSecret);
    }

    /**
     * Recherche sur Discogs
     */
    public function search(string $query, ?string $format = null, ?string $type = null, int $limit = 20): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $params = [
                'q' => $query,
                'key' => $this->consumerKey,
                'secret' => $this->consumerSecret,
                'per_page' => $limit,
                'type' => 'release',
            ];

            // Format (CD, Vinyl, Cassette)
            if ($format) {
                $params['format'] = $this->mapFormat($format);
            }

            $response = $this->httpClient->request('GET', 'https://api.discogs.com/database/search', [
                'query' => $params,
                'headers' => [
                    'User-Agent' => 'BDD-Hub/1.0',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            $results = [];
            foreach ($data['results'] ?? [] as $item) {
                // Extraire artiste et titre
                $title = $item['title'] ?? '';
                $parts = explode(' - ', $title, 2);
                $artiste = $parts[0] ?? '';
                $titre = $parts[1] ?? $title;

                $results[] = [
                    'id' => $item['id'] ?? '',
                    'titre' => trim($titre),
                    'artiste' => trim($artiste),
                    'format' => $this->normalizeFormat($item['format'] ?? []),
                    'type' => $this->normalizeType($item['type'] ?? ''),
                    'annee' => $item['year'] ?? null,
                    'label' => is_array($item['label'] ?? null) ? ($item['label'][0] ?? '') : ($item['label'] ?? ''),
                    'genre' => is_array($item['genre'] ?? null) ? implode(', ', $item['genre']) : ($item['genre'] ?? ''),
                    'cover' => $item['cover_image'] ?? $item['thumb'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche par code-barres (EAN/UPC)
     */
    public function searchByBarcode(string $barcode): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $params = [
                'barcode' => $barcode,
                'key' => $this->consumerKey,
                'secret' => $this->consumerSecret,
                'per_page' => 10,
            ];

            $response = $this->httpClient->request('GET', 'https://api.discogs.com/database/search', [
                'query' => $params,
                'headers' => [
                    'User-Agent' => 'BDD-Hub/1.0',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            $results = [];
            foreach ($data['results'] ?? [] as $item) {
                $title = $item['title'] ?? '';
                $parts = explode(' - ', $title, 2);
                $artiste = $parts[0] ?? '';
                $titre = $parts[1] ?? $title;

                $results[] = [
                    'id' => $item['id'] ?? '',
                    'release_id' => $item['id'] ?? '',
                    'is_master' => false,
                    'titre' => trim($titre),
                    'artiste' => trim($artiste),
                    'format' => $this->normalizeFormat($item['format'] ?? []),
                    'type' => $this->normalizeType($item['type'] ?? ''),
                    'annee' => $item['year'] ?? null,
                    'label' => is_array($item['label'] ?? null) ? ($item['label'][0] ?? '') : ($item['label'] ?? ''),
                    'genre' => is_array($item['genre'] ?? null) ? implode(', ', $item['genre']) : ($item['genre'] ?? ''),
                    'cover' => $item['cover_image'] ?? $item['thumb'] ?? '',
                    'barcode' => $barcode,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les éditions d'un master
     */
    public function getMasterReleases(string $masterId, int $limit = 10): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.discogs.com/masters/' . $masterId . '/versions', [
                'query' => [
                    'key' => $this->consumerKey,
                    'secret' => $this->consumerSecret,
                    'per_page' => $limit,
                ],
                'headers' => [
                    'User-Agent' => 'BDD-Hub/1.0',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            $releases = [];
            foreach ($data['versions'] ?? [] as $version) {
                $releases[] = [
                    'id' => $version['id'] ?? '',
                    'format' => $this->normalizeFormat([$version['format'] ?? '']),
                    'label' => $version['label'] ?? '',
                    'country' => $version['country'] ?? '',
                    'annee' => $version['released'] ?? null,
                    'catno' => $version['catno'] ?? '',
                ];
            }

            return $releases;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les détails d'un master release par son ID
     */
    public function getMasterDetails(string $masterId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.discogs.com/masters/' . $masterId, [
                'query' => [
                    'key' => $this->consumerKey,
                    'secret' => $this->consumerSecret,
                ],
                'headers' => [
                    'User-Agent' => 'BDD-Hub/1.0',
                ],
                'timeout' => 10,
            ]);

            $item = $response->toArray();

            // Extraire les artistes
            $artistes = [];
            foreach ($item['artists'] ?? [] as $artist) {
                $artistes[] = $artist['name'] ?? '';
            }

            // Extraire les genres
            $genres = array_merge($item['genres'] ?? [], $item['styles'] ?? []);

            // Tracklist
            $tracklist = [];
            foreach ($item['tracklist'] ?? [] as $track) {
                $trackType = $track['type_'] ?? 'track';
                if ($trackType === 'track' || $trackType === '') {
                    $tracklist[] = [
                        'position' => $track['position'] ?? '',
                        'title' => $track['title'] ?? '',
                        'duration' => $track['duration'] ?? '',
                    ];
                }
            }

            return [
                'id' => $item['id'] ?? '',
                'master_id' => $item['id'] ?? '',
                'main_release_id' => $item['main_release'] ?? null,
                'titre' => $item['title'] ?? '',
                'artiste' => implode(', ', $artistes),
                'format' => 'cd', // Par défaut, le master n'a pas de format spécifique
                'type' => 'album',
                'annee' => $item['year'] ?? null,
                'label' => '', // Le master n'a pas de label, c'est sur les releases
                'genre' => implode(', ', array_unique($genres)),
                'description' => $item['notes'] ?? '',
                'cover' => $item['images'][0]['uri'] ?? '',
                'tracklist' => $tracklist,
                'is_master' => true,
                'versions_count' => $item['versions_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Récupère les détails d'une release par son ID
     */
    public function getDetails(string $releaseId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.discogs.com/releases/' . $releaseId, [
                'query' => [
                    'key' => $this->consumerKey,
                    'secret' => $this->consumerSecret,
                ],
                'headers' => [
                    'User-Agent' => 'BDD-Hub/1.0',
                ],
                'timeout' => 10,
            ]);

            $item = $response->toArray();

            // Extraire les artistes
            $artistes = [];
            foreach ($item['artists'] ?? [] as $artist) {
                $artistes[] = $artist['name'] ?? '';
            }

            // Extraire les labels
            $labels = [];
            foreach ($item['labels'] ?? [] as $label) {
                $labels[] = $label['name'] ?? '';
            }

            // Extraire les genres
            $genres = array_merge($item['genres'] ?? [], $item['styles'] ?? []);

            // Tracklist
            $tracklist = [];
            foreach ($item['tracklist'] ?? [] as $track) {
                if (($track['type_'] ?? 'track') === 'track') {
                    $tracklist[] = [
                        'position' => $track['position'] ?? '',
                        'title' => $track['title'] ?? '',
                        'duration' => $track['duration'] ?? '',
                    ];
                }
            }

            // Extraire le format détaillé
            $formatDetails = [];
            foreach ($item['formats'] ?? [] as $fmt) {
                $fmtName = $fmt['name'] ?? '';
                $fmtDesc = implode(', ', $fmt['descriptions'] ?? []);
                $formatDetails[] = $fmtName . ($fmtDesc ? ' (' . $fmtDesc . ')' : '');
            }

            return [
                'id' => $item['id'] ?? '',
                'master_id' => $item['master_id'] ?? null,
                'titre' => $item['title'] ?? '',
                'artiste' => implode(', ', $artistes),
                'format' => $this->normalizeFormat($item['formats'] ?? []),
                'format_detail' => implode(', ', $formatDetails),
                'type' => $this->normalizeType($item['type'] ?? 'release'),
                'annee' => $item['year'] ?? null,
                'label' => implode(', ', $labels),
                'genre' => implode(', ', array_unique($genres)),
                'description' => $item['notes'] ?? '',
                'cover' => $item['images'][0]['uri'] ?? $item['thumb'] ?? '',
                'tracklist' => $tracklist,
                'country' => $item['country'] ?? '',
                'is_master' => false,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function mapFormat(string $format): string
    {
        return match ($format) {
            'cd' => 'CD',
            'vinyle' => 'Vinyl',
            'k7' => 'Cassette',
            'digital' => 'File',
            default => $format,
        };
    }

    private function normalizeFormat(array $formats): string
    {
        if (empty($formats)) {
            return 'cd';
        }

        // Formats peut être un tableau d'objets ou de strings
        $formatStr = '';
        if (is_array($formats[0] ?? null)) {
            $formatStr = strtolower($formats[0]['name'] ?? '');
        } else {
            $formatStr = strtolower($formats[0] ?? '');
        }

        if (str_contains($formatStr, 'vinyl') || str_contains($formatStr, 'lp')) {
            return 'vinyle';
        }
        if (str_contains($formatStr, 'cassette') || str_contains($formatStr, 'k7')) {
            return 'k7';
        }
        if (str_contains($formatStr, 'file') || str_contains($formatStr, 'digital')) {
            return 'digital';
        }
        return 'cd';
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower($type);
        if (str_contains($type, 'single')) {
            return 'single';
        }
        if (str_contains($type, 'compilation')) {
            return 'compilation';
        }
        if (str_contains($type, 'ep')) {
            return 'ep';
        }
        return 'album';
    }
}
