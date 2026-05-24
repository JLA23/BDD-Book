<?php

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stockage local des images sous public/uploads/{type}/.
 * Conserve l'URL source en base ; stored_path pointe vers le fichier local.
 */
class ImageStorageService
{
    private string $uploadsDir;
    private ?HttpClientInterface $httpClient;

    public function __construct(string $projectDir, ?HttpClientInterface $httpClient = null)
    {
        $this->uploadsDir = $projectDir . '/public/uploads';
        $this->httpClient = $httpClient;
    }

    public function getUploadsDir(): string
    {
        return $this->uploadsDir;
    }

    public function getTypeDir(string $mediaType): string
    {
        $dir = $this->uploadsDir . '/' . $mediaType;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function toWebPath(string $mediaType, string $filename): string
    {
        return '/uploads/' . $mediaType . '/' . $filename;
    }

    public function isLocalWebPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return str_starts_with($path, '/uploads/');
    }

    public function isRemoteUrl(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return (bool) preg_match('#^https?://#i', $path);
    }

    /**
     * Télécharge ou copie une source vers le dossier du type et retourne le chemin web local.
     *
     * @param string|null $sourceUrl     URL http(s), chemin /uploads/... ou data:image/...
     * @param string|null $existingStored Chemin déjà enregistré (ignoré si $force = false et fichier présent)
     */
    public function mirrorToStorage(
        ?string $sourceUrl,
        string $mediaType,
        string $basename,
        ?string $existingStored = null,
        bool $force = false
    ): ?string {
        if ($sourceUrl === null || $sourceUrl === '') {
            return $existingStored;
        }

        if (!$force && $existingStored !== null && $existingStored !== '') {
            if ($this->fileExistsForWebPath($existingStored)) {
                return $existingStored;
            }
        }

        $binary = $this->readBinaryFromSource($sourceUrl);
        if ($binary === null || $binary === '') {
            return $existingStored;
        }

        $extension = $this->guessExtension($sourceUrl, $binary);
        $filename = $this->sanitizeBasename($basename) . '.' . $extension;
        $targetDir = $this->getTypeDir($mediaType);
        $targetFile = $targetDir . '/' . $filename;

        if ($force && is_file($targetFile)) {
            @unlink($targetFile);
        }

        if (file_put_contents($targetFile, $binary) === false) {
            return $existingStored;
        }

        return $this->toWebPath($mediaType, $filename);
    }

    public function storeUploadedFile(UploadedFile $file, string $mediaType, string $basename): ?string
    {
        $extension = $file->guessExtension() ?: 'jpg';
        $filename = $this->sanitizeBasename($basename) . '.' . $extension;
        $file->move($this->getTypeDir($mediaType), $filename);

        return $this->toWebPath($mediaType, $filename);
    }

    public function storeBinary(string $binary, string $mediaType, string $basename, ?string $extension = null): ?string
    {
        if ($binary === '') {
            return null;
        }

        $extension = $extension ?? $this->guessExtension(null, $binary);
        $filename = $this->sanitizeBasename($basename) . '.' . $extension;
        $path = $this->getTypeDir($mediaType) . '/' . $filename;

        if (file_put_contents($path, $binary) === false) {
            return null;
        }

        return $this->toWebPath($mediaType, $filename);
    }

    public function fileExistsForWebPath(?string $webPath): bool
    {
        if (!$this->isLocalWebPath($webPath)) {
            return false;
        }

        $absolute = $this->uploadsDir . substr($webPath, strlen('/uploads'));

        return is_file($absolute);
    }

    public function resolveDisplayPath(?string $storedPath, ?string $legacyLocal, ?string $remoteUrl): ?string
    {
        if ($storedPath !== null && $storedPath !== '' && $this->fileExistsForWebPath($storedPath)) {
            return $storedPath;
        }

        if ($legacyLocal !== null && $legacyLocal !== '') {
            if ($this->isLocalWebPath($legacyLocal) && $this->fileExistsForWebPath($legacyLocal)) {
                return $legacyLocal;
            }
            if (!$this->isRemoteUrl($legacyLocal)) {
                $legacyPath = str_starts_with($legacyLocal, '/uploads/')
                    ? $legacyLocal
                    : $this->resolveLegacyFilenamePath($legacyLocal);
                if ($legacyPath !== null && $this->fileExistsForWebPath($legacyPath)) {
                    return $legacyPath;
                }
            }
        }

        return $remoteUrl;
    }

    /**
     * Ancien stockage livres : image2 = nom de fichier dans covers/.
     */
    public function resolveBookLegacyPath(?string $image2Filename): ?string
    {
        if ($image2Filename === null || $image2Filename === '') {
            return null;
        }

        if ($this->isLocalWebPath($image2Filename)) {
            return $image2Filename;
        }

        $covers = '/uploads/covers/' . ltrim($image2Filename, '/');
        if ($this->fileExistsForWebPath($covers)) {
            return $covers;
        }

        $books = '/uploads/' . ImageMediaType::BOOK . '/' . ltrim($image2Filename, '/');
        if ($this->fileExistsForWebPath($books)) {
            return $books;
        }

        return null;
    }

    private function resolveLegacyFilenamePath(string $filename): ?string
    {
        $candidates = [
            '/uploads/covers/' . ltrim($filename, '/'),
            '/uploads/' . ImageMediaType::BOOK . '/' . ltrim($filename, '/'),
        ];

        foreach ($candidates as $path) {
            if ($this->fileExistsForWebPath($path)) {
                return $path;
            }
        }

        return null;
    }

    private function readBinaryFromSource(string $source): ?string
    {
        if (str_starts_with($source, 'data:image/')) {
            $parts = explode(',', $source, 2);

            return isset($parts[1]) ? base64_decode($parts[1], true) ?: null : null;
        }

        if ($this->isLocalWebPath($source)) {
            $absolute = $this->uploadsDir . substr($source, strlen('/uploads'));
            if (!is_file($absolute)) {
                return null;
            }

            return file_get_contents($absolute) ?: null;
        }

        if ($this->isRemoteUrl($source)) {
            return $this->downloadUrl($source);
        }

        return null;
    }

    private function downloadUrl(string $url): ?string
    {
        try {
            if ($this->httpClient !== null) {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 20,
                    'max_redirects' => 5,
                ]);
                if ($response->getStatusCode() >= 400) {
                    return null;
                }

                return $response->getContent();
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'follow_location' => 1,
                    'user_agent' => 'Bdd-Books/1.0 ImageFetcher',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $content = @file_get_contents($url, false, $context);

            return $content !== false ? $content : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function guessExtension(?string $source, string $binary): string
    {
        if ($source !== null && preg_match('#\.(jpe?g|png|gif|webp)(\?|$)#i', $source, $m)) {
            return strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
        }

        $info = @getimagesizefromstring($binary);
        if ($info !== false && isset($info[2])) {
            return match ($info[2]) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_GIF => 'gif',
                IMAGETYPE_WEBP => 'webp',
                default => 'jpg',
            };
        }

        return 'jpg';
    }

    private function sanitizeBasename(string $basename): string
    {
        $basename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $basename) ?? 'image';
        $basename = trim($basename, '-');

        return $basename !== '' ? $basename : 'image';
    }
}
