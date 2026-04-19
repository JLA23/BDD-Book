<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GameApiService
{
    private HttpClientInterface $httpClient;
    private ?string $twitchClientId;
    private ?string $twitchClientSecret;
    private ?string $deeplApiKey;
    private ?string $accessToken = null;
    private ?int $tokenExpires = null;

    public function __construct(
        HttpClientInterface $httpClient, 
        ?string $twitchClientId = null, 
        ?string $twitchClientSecret = null,
        ?string $deeplApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->twitchClientId = $twitchClientId;
        $this->twitchClientSecret = $twitchClientSecret;
        $this->deeplApiKey = $deeplApiKey;
    }

    public function isConfigured(): bool
    {
        return !empty($this->twitchClientId) && !empty($this->twitchClientSecret);
    }

    public function isTranslationConfigured(): bool
    {
        return !empty($this->deeplApiKey);
    }

    /**
     * Obtient un token d'accès OAuth2 pour IGDB via Twitch
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken && $this->tokenExpires && time() < $this->tokenExpires) {
            return $this->accessToken;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://id.twitch.tv/oauth2/token', [
                'body' => [
                    'client_id' => $this->twitchClientId,
                    'client_secret' => $this->twitchClientSecret,
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = $response->toArray();
            $this->accessToken = $data['access_token'];
            $this->tokenExpires = time() + ($data['expires_in'] ?? 3600) - 60;
            
            return $this->accessToken;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Traduit un texte de l'anglais vers le français via DeepL API
     */
    public function translateToFrench(string $text): string
    {
        if (!$this->isTranslationConfigured() || empty($text)) {
            return $text;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api-free.deepl.com/v2/translate', [
                'headers' => [
                    'Authorization' => 'DeepL-Auth-Key ' . $this->deeplApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'text' => [$text],
                    'target_lang' => 'FR',
                ],
            ]);

            $data = $response->toArray();
            return $data['translations'][0]['text'] ?? $text;
        } catch (\Exception $e) {
            // Log error for debugging
            error_log('DeepL translation error: ' . $e->getMessage());
            return $text;
        }
    }

    /**
     * Recherche des jeux par titre via IGDB API
     */
    public function searchGames(string $query, int $limit = 10): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->twitchClientId,
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body' => "search \"{$query}\"; 
                    fields name, first_release_date, cover.url, genres.name, platforms.name, rating, aggregated_rating;
                    limit {$limit};",
            ]);

            $games = $response->toArray();
            $results = [];

            foreach ($games as $game) {
                $cover = null;
                if (isset($game['cover']['url'])) {
                    // Convertir en grande image (t_cover_big)
                    $cover = str_replace('t_thumb', 't_cover_big', $game['cover']['url']);
                    if (!str_starts_with($cover, 'http')) {
                        $cover = 'https:' . $cover;
                    }
                }

                $results[] = [
                    'id' => $game['id'],
                    'titre' => $game['name'],
                    'annee' => isset($game['first_release_date']) ? (int) date('Y', $game['first_release_date']) : null,
                    'cover' => $cover,
                    'genres' => implode(', ', array_map(fn($g) => $g['name'], $game['genres'] ?? [])),
                    'platforms' => array_map(fn($p) => $p['name'], $game['platforms'] ?? []),
                    'rating' => isset($game['rating']) ? round($game['rating'] / 10, 1) : null,
                    'metacritic' => isset($game['aggregated_rating']) ? round($game['aggregated_rating']) : null,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les détails d'un jeu par son ID IGDB
     */
    public function getGameDetails(int $gameId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.igdb.com/v4/games', [
                'headers' => [
                    'Client-ID' => $this->twitchClientId,
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body' => "where id = {$gameId};
                    fields name, first_release_date, summary, storyline, 
                           cover.url, screenshots.url,
                           genres.name, platforms.name, platforms.abbreviation,
                           involved_companies.company.name, involved_companies.developer, involved_companies.publisher,
                           age_ratings.*,
                           websites.url, websites.category,
                           aggregated_rating;",
            ]);

            $games = $response->toArray();
            if (empty($games)) {
                return null;
            }

            $game = $games[0];

            // Récupérer éditeurs et développeurs
            $publishers = [];
            $developers = [];
            foreach ($game['involved_companies'] ?? [] as $ic) {
                if ($ic['publisher'] ?? false) {
                    $publishers[] = $ic['company']['name'];
                }
                if ($ic['developer'] ?? false) {
                    $developers[] = $ic['company']['name'];
                }
            }

            // Classification PEGI (category 2 = PEGI)
            $classification = null;
            foreach ($game['age_ratings'] ?? [] as $ar) {
                // category 1 = ESRB, category 2 = PEGI
                if (($ar['category'] ?? 0) == 2) {
                    // PEGI ratings: 1=3, 2=7, 3=12, 4=16, 5=18
                    $pegiMap = [1 => 'PEGI 3', 2 => 'PEGI 7', 3 => 'PEGI 12', 4 => 'PEGI 16', 5 => 'PEGI 18'];
                    $classification = $pegiMap[$ar['rating'] ?? 0] ?? null;
                    break;
                }
            }

            // Cover
            $cover = null;
            if (isset($game['cover']['url'])) {
                $cover = str_replace('t_thumb', 't_cover_big', $game['cover']['url']);
                if (!str_starts_with($cover, 'http')) {
                    $cover = 'https:' . $cover;
                }
            }

            // Screenshots
            $screenshots = [];
            foreach ($game['screenshots'] ?? [] as $s) {
                $url = str_replace('t_thumb', 't_screenshot_big', $s['url']);
                if (!str_starts_with($url, 'http')) {
                    $url = 'https:' . $url;
                }
                $screenshots[] = $url;
            }

            // Site officiel
            $website = null;
            foreach ($game['websites'] ?? [] as $w) {

            }

            // Description (summary ou storyline)
            $description = $game['summary'] ?? $game['storyline'] ?? '';
            $genres = implode(', ', array_map(fn($g) => $g['name'], $game['genres'] ?? []));
            
            if ($this->isTranslationConfigured()) {
                if (!empty($description)) {
                    $description = $this->translateToFrench($description);
                }
                if (!empty($genres)) {
                    $genres = $this->translateToFrench($genres);
                }
            }

            // Plateformes avec slug - utiliser le nom complet en minuscules pour un meilleur mapping
            $platforms = [];
            foreach ($game['platforms'] ?? [] as $p) {
                $name = $p['name'];
                // Créer un slug basé sur le nom complet
                $slug = strtolower(str_replace(' ', '', $name));
                $platforms[] = [
                    'name' => $name,
                    'slug' => $slug,
                ];
            }

            return [
                'id' => $game['id'],
                'titre' => $game['name'],
                'annee' => isset($game['first_release_date']) ? (int) date('Y', $game['first_release_date']) : null,
                'editeur' => implode(', ', $publishers),
                'developpeur' => implode(', ', $developers),
                'genre' => $genres,
                'classification' => $classification,
                'description' => $description,
                'cover' => $cover,
                'platforms' => $platforms,
                'screenshots' => $screenshots,
                'metacritic' => isset($game['aggregated_rating']) ? round($game['aggregated_rating']) : null,
                'website' => $website,
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('IGDB error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convertit une abréviation de plateforme IGDB en code console
     */
    public function platformToConsole(string $platformSlug): string
    {
        $slug = strtolower($platformSlug);
        return match ($slug) {
            'ps5' => 'PS5',
            'ps4' => 'PS4',
            'ps3' => 'PS3',
            'ps2' => 'PS2',
            'ps1', 'psx', 'playstation' => 'PS1',
            'vita', 'psvita' => 'PSVita',
            'psp' => 'PSP',
            'series x', 'xsx', 'series x/s' => 'XSX',
            'xone', 'xbox one' => 'XOne',
            'x360', 'xbox 360' => 'X360',
            'xbox' => 'Xbox',
            'switch', 'nintendo switch' => 'Switch',
            'wiiu', 'wii u' => 'WiiU',
            'wii' => 'Wii',
            'gc', 'gamecube', 'ngc' => 'GameCube',
            'n64', 'nintendo 64' => 'N64',
            '3ds', 'nintendo 3ds' => '3DS',
            'nds', 'ds', 'nintendo ds' => 'DS',
            'gba', 'game boy advance' => 'GBA',
            'gb', 'gbc', 'game boy', 'game boy color' => 'GB',
            'pc', 'win', 'windows' => 'PC',
            'mac', 'macos' => 'Mac',
            'linux' => 'Linux',
            'android' => 'Android',
            'ios' => 'iOS',
            default => ucfirst($platformSlug),
        };
    }
}
