<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DvdApiService
{
    private HttpClientInterface $httpClient;
    private ?string $apiKey;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['DVDFR_API_KEY'] ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Recherche de DVD/Blu-ray sur DVDFR
     */
    public function search(string $query, ?string $format = null, int $limit = 20): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $url = 'https://www.dvdfr.com/api/search.php';
            $params = [
                'title' => $query,
                'key' => $this->apiKey,
            ];

            if ($format) {
                $params['format'] = $format;
            }

            $response = $this->httpClient->request('GET', $url, [
                'query' => $params,
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            $results = [];
            foreach ($data['results'] ?? [] as $item) {
                $results[] = [
                    'id' => $item['id'] ?? '',
                    'titre' => $item['title'] ?? '',
                    'format' => $this->normalizeFormat($item['format'] ?? ''),
                    'annee' => $item['year'] ?? null,
                    'editeur' => $item['editor'] ?? '',
                    'cover' => $item['cover'] ?? '',
                    'type' => $this->guessType($item['title'] ?? ''),
                ];

                if (count($results) >= $limit) {
                    break;
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les détails d'un DVD par son ID
     */
    public function getDetails(string $dvdId): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://www.dvdfr.com/api/detail.php', [
                'query' => [
                    'id' => $dvdId,
                    'key' => $this->apiKey,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $item = $data['dvd'] ?? [];

            return [
                'id' => $item['id'] ?? '',
                'titre' => $item['title'] ?? '',
                'format' => $this->normalizeFormat($item['format'] ?? ''),
                'annee' => $item['year'] ?? null,
                'editeur' => $item['editor'] ?? '',
                'description' => $item['synopsis'] ?? '',
                'cover' => $item['cover'] ?? '',
                'type' => $this->guessType($item['title'] ?? ''),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower($format);
        if (str_contains($format, '4k') || str_contains($format, 'uhd')) {
            return 'bluray4k';
        }
        if (str_contains($format, 'blu') || str_contains($format, 'bd')) {
            return 'bluray';
        }
        return 'dvd';
    }

    private function guessType(string $titre): string
    {
        $titreLower = strtolower($titre);
        if (str_contains($titreLower, 'saison') || str_contains($titreLower, 'season') || str_contains($titreLower, 'integrale') || str_contains($titreLower, 'coffret')) {
            return 'serie';
        }
        return 'film';
    }
}
