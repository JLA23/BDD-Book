<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Télécharge une page. Si le site répond par un blocage (Cloudflare, Akamai),
 * relance la requête via Byparr / FlareSolverr.
 */
class RemotePageClient
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $solverUrl = '',
    ) {
    }

    public function get(string $url): ?string
    {
        $body = $this->getDirect($url);
        if ($body !== null && !$this->isBlocked($body)) {
            return $body;
        }

        return $this->getViaSolver($url);
    }

    private function getDirect(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ],
                'timeout' => 20,
                'max_redirects' => 5,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                return null;
            }
            $body = $response->getContent(false);

            return $body !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getViaSolver(string $url): ?string
    {
        $solverUrl = trim($this->solverUrl);
        if ($solverUrl === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($solverUrl, '/') . '/v1', [
                'json' => [
                    'cmd' => 'request.get',
                    'url' => $url,
                    'maxTimeout' => 60000,
                ],
                'timeout' => 70,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $data = $response->toArray(false);
            $body = $data['solution']['response'] ?? null;
            if (!is_string($body) || $body === '' || $this->isBlocked($body)) {
                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            error_log('Remote page solver error: ' . $e->getMessage());

            return null;
        }
    }

    private function isBlocked(string $body): bool
    {
        return str_contains($body, 'Just a moment')
            || str_contains($body, 'cf-turnstile-response')
            || str_contains($body, 'Vérification de sécurité')
            || str_contains($body, 'FNAC DARTY - Maintenance')
            || str_contains($body, 'Access Denied')
            || str_contains($body, 'captcha-delivery.com');
    }
}
