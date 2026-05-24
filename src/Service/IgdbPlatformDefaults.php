<?php

declare(strict_types=1);

namespace App\Service;

/**
 * IDs plateforme IGDB (endpoint /v4/platforms) par code console interne.
 * Vérifiés via l’API IGDB — ne pas confondre avec d’anciennes listes erronées.
 */
final class IgdbPlatformDefaults
{
    /** @var array<string, int> */
    public const BY_CONSOLE_CODE = [
        'PS5' => 167,
        'PS4' => 48,
        'PS3' => 9,
        'PS2' => 8,
        'PS1' => 7,
        'PSVita' => 46,
        'PSP' => 38,
        'XSX' => 169,
        'XOne' => 49,
        'X360' => 12,
        'Xbox' => 11,
        'Switch' => 130,
        'WiiU' => 41,
        'Wii' => 5,
        'GameCube' => 21,
        'N64' => 4,
        '3DS' => 37,
        'DS' => 20,
        'GBA' => 24,
        'GB' => 33,
        'PC' => 6,
        'Mac' => 14,
        'Linux' => 3,
        'Android' => 34,
        'iOS' => 39,
    ];

    public static function forConsoleCode(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::BY_CONSOLE_CODE[$code] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function consoleCodes(): array
    {
        return array_keys(self::BY_CONSOLE_CODE);
    }
}
