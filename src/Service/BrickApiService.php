<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour les APIs Rebrickable et Brickset
 * 
 * Rebrickable: https://rebrickable.com/api/v3/docs/
 * Brickset: https://brickset.com/api/v3.asmx
 * 
 * Pour obtenir les clés API:
 * - Rebrickable: Settings > API sur rebrickable.com
 * - Brickset: https://brickset.com/tools/webservices/requestkey
 */
class BrickApiService
{
    private HttpClientInterface $httpClient;
    private string $rebrickableKey;
    private string $bricksetKey;
    private string $rebrickableUrl = 'https://rebrickable.com/api/v3/lego';
    private string $bricksetUrl = 'https://brickset.com/api/v3.asmx';

    public function __construct(
        HttpClientInterface $httpClient, 
        ?string $rebrickableApiKey = null,
        ?string $bricksetApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->rebrickableKey = $rebrickableApiKey ?? '';
        $this->bricksetKey = $bricksetApiKey ?? '';
    }

    public function isRebrickableConfigured(): bool
    {
        return !empty($this->rebrickableKey);
    }

    public function isBricksetConfigured(): bool
    {
        return !empty($this->bricksetKey);
    }

    public function isConfigured(): bool
    {
        return $this->isRebrickableConfigured() || $this->isBricksetConfigured();
    }

    /**
     * Recherche un set par référence - combine Rebrickable et Brickset
     */
    public function searchSet(string $reference): ?array
    {
        $result = null;
        $images = [];

        // Rechercher sur Rebrickable (données uniquement, pas d'images)
        if ($this->isRebrickableConfigured()) {
            $result = $this->getRebrickableSet($reference);
        }

        // Compléter avec Brickset (images + prix)
        if ($this->isBricksetConfigured()) {
            $bricksetData = $this->getBricksetSet($reference);
            if ($bricksetData) {
                if (!$result) {
                    $result = $bricksetData;
                } else {
                    // Compléter avec les données Brickset (prix)
                    if (empty($result['prix']) && !empty($bricksetData['prix'])) {
                        $result['prix'] = $bricksetData['prix'];
                    }
                    // Utiliser l'image Brickset si disponible
                    if (!empty($bricksetData['image'])) {
                        $result['image'] = $bricksetData['image'];
                    }
                }
                // Récupérer les images uniquement depuis Brickset
                $images = $this->getBricksetImages($reference);
            }
        }

        if ($result) {
            $result['images'] = $this->deduplicateImages($images);
        }

        return $result;
    }

    /**
     * Recherche sur Rebrickable
     */
    private function getRebrickableSet(string $setNum): ?array
    {
        $setNum = $this->normalizeSetNum($setNum);

        try {
            $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/sets/' . $setNum . '/', [
                'headers' => [
                    'Authorization' => 'key ' . $this->rebrickableKey,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                // Récupérer le thème
                $themeName = null;
                if (!empty($data['theme_id'])) {
                    $themeName = $this->getRebrickableTheme($data['theme_id']);
                }

                return [
                    'nom' => $data['name'] ?? '',
                    'reference' => $data['set_num'] ?? $setNum,
                    'annee' => $data['year'] ?? null,
                    'nbPieces' => $data['num_parts'] ?? null,
                    'image' => $data['set_img_url'] ?? null,
                    'theme' => $themeName,
                    'prix' => null, // Rebrickable n'a pas le prix
                    'source' => 'Rebrickable',
                ];
            }
        } catch (\Exception $e) {
            // Ignorer
        }

        return null;
    }

    /**
     * Recherche sur Brickset
     */
    private function getBricksetSet(string $setNum): ?array
    {
        // Enlever le suffixe -1 pour Brickset
        $setNum = preg_replace('/-\d+$/', '', $setNum);

        try {
            // Brickset API v3 utilise POST avec paramètres en body
            $response = $this->httpClient->request('POST', $this->bricksetUrl . '/getSets', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'apiKey' => $this->bricksetKey,
                    'userHash' => '',
                    'params' => json_encode(['query' => $setNum]),
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                if ($data['status'] === 'success' && !empty($data['sets'][0])) {
                    $set = $data['sets'][0];
                    
                    // Récupérer le prix (EUR = DE de préférence)
                    $prix = null;
                    if (!empty($set['LEGOCom']['DE']['retailPrice'])) {
                        $prix = $set['LEGOCom']['DE']['retailPrice'];
                    } elseif (!empty($set['LEGOCom']['US']['retailPrice'])) {
                        $prix = $set['LEGOCom']['US']['retailPrice'];
                    }

                    return [
                        'nom' => $set['name'] ?? '',
                        'reference' => $set['number'] ?? $setNum,
                        'annee' => $set['year'] ?? null,
                        'nbPieces' => $set['pieces'] ?? null,
                        'image' => $set['image']['imageURL'] ?? null,
                        'theme' => $set['theme'] ?? null,
                        'subtheme' => $set['subtheme'] ?? null,
                        'prix' => $prix,
                        'bricksetId' => $set['setID'] ?? null,
                        'source' => 'Brickset',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log pour debug
            error_log('Brickset API error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Images depuis Rebrickable
     */
    private function getRebrickableImages(string $setNum): array
    {
        $setNum = $this->normalizeSetNum($setNum);
        $images = [];

        try {
            // Image principale
            $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/sets/' . $setNum . '/', [
                'headers' => ['Authorization' => 'key ' . $this->rebrickableKey],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                if (!empty($data['set_img_url'])) {
                    $images[] = [
                        'url' => $data['set_img_url'],
                        'source' => 'Rebrickable',
                        'type' => 'main',
                    ];
                }
            }

            // Images alternatives
            $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/sets/' . $setNum . '/images/', [
                'headers' => ['Authorization' => 'key ' . $this->rebrickableKey],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                foreach ($data['results'] ?? [] as $img) {
                    if (!empty($img['img_url'])) {
                        $images[] = [
                            'url' => $img['img_url'],
                            'source' => 'Rebrickable',
                            'type' => 'alternate',
                        ];
                    }
                }
            }

            // Minifigs
            $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/sets/' . $setNum . '/minifigs/', [
                'headers' => ['Authorization' => 'key ' . $this->rebrickableKey],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                foreach ($data['results'] ?? [] as $minifig) {
                    if (!empty($minifig['set_img_url'])) {
                        $images[] = [
                            'url' => $minifig['set_img_url'],
                            'source' => 'Minifig: ' . ($minifig['set_name'] ?? ''),
                            'type' => 'minifig',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignorer
        }

        return $images;
    }

    /**
     * Images depuis Brickset (getAdditionalImages)
     */
    private function getBricksetImages(string $setNum): array
    {
        $setNum = preg_replace('/-\d+$/', '', $setNum);
        $images = [];

        try {
            // D'abord récupérer le setID via POST
            $response = $this->httpClient->request('POST', $this->bricksetUrl . '/getSets', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => [
                    'apiKey' => $this->bricksetKey,
                    'userHash' => '',
                    'params' => json_encode(['query' => $setNum]),
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                if ($data['status'] === 'success' && !empty($data['sets'][0])) {
                    $set = $data['sets'][0];
                    $setId = $set['setID'];

                    // Image principale
                    if (!empty($set['image']['imageURL'])) {
                        $images[] = [
                            'url' => $set['image']['imageURL'],
                            'source' => 'Brickset',
                            'type' => 'main',
                        ];
                    }

                    // Images additionnelles via POST
                    $response = $this->httpClient->request('POST', $this->bricksetUrl . '/getAdditionalImages', [
                        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                        'body' => [
                            'apiKey' => $this->bricksetKey,
                            'setID' => $setId,
                        ],
                    ]);

                    if ($response->getStatusCode() === 200) {
                        $imgData = $response->toArray();
                        foreach ($imgData['additionalImages'] ?? [] as $img) {
                            // Ne prendre que les images full size, pas les thumbnails
                            if (!empty($img['imageURL'])) {
                                $images[] = [
                                    'url' => $img['imageURL'],
                                    'source' => 'Brickset',
                                    'type' => 'alternate',
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignorer
        }

        return $images;
    }

    /**
     * Récupère le nom du thème Rebrickable
     */
    private function getRebrickableTheme(int $themeId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/themes/' . $themeId . '/', [
                'headers' => ['Authorization' => 'key ' . $this->rebrickableKey],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['name'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignorer
        }

        return null;
    }

    /**
     * Recherche de sets par mot-clé
     */
    public function searchSets(string $search, int $limit = 20): array
    {
        $results = [];

        if ($this->isRebrickableConfigured()) {
            try {
                $response = $this->httpClient->request('GET', $this->rebrickableUrl . '/sets/', [
                    'headers' => ['Authorization' => 'key ' . $this->rebrickableKey],
                    'query' => ['search' => $search, 'page_size' => $limit],
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    foreach ($data['results'] ?? [] as $set) {
                        $results[] = [
                            'nom' => $set['name'] ?? '',
                            'reference' => $set['set_num'] ?? '',
                            'annee' => $set['year'] ?? null,
                            'nbPieces' => $set['num_parts'] ?? null,
                            'image' => $set['set_img_url'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }

        return $results;
    }

    /**
     * Normalise le numéro de set pour Rebrickable (ajoute -1)
     */
    private function normalizeSetNum(string $setNum): string
    {
        if (!str_contains($setNum, '-')) {
            return $setNum . '-1';
        }
        return $setNum;
    }

    /**
     * Déduplique les images par URL
     */
    private function deduplicateImages(array $images): array
    {
        $seen = [];
        $result = [];

        foreach ($images as $img) {
            $url = $img['url'] ?? '';
            if ($url && !in_array($url, $seen)) {
                $seen[] = $url;
                $result[] = $img;
            }
        }

        return $result;
    }
}
