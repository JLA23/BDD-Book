<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Données initiales uniquement : insertion dans game_console_alias (commande / migration).
 * La résolution à l'exécution lit la base, pas ce fichier.
 */
final class ConsoleSlugAliasSeedData
{
    /** Slug normalisé (sans espaces, minuscules) ou libellé → code console */
    private const SLUG_OR_LABEL_TO_CODE = [
        'playstation5' => 'PS5',
        'ps5' => 'PS5',
        'playstation4' => 'PS4',
        'ps4' => 'PS4',
        'playstation3' => 'PS3',
        'ps3' => 'PS3',
        'playstation2' => 'PS2',
        'ps2' => 'PS2',
        'playstation' => 'PS1',
        'playstation1' => 'PS1',
        'ps1' => 'PS1',
        'psx' => 'PS1',
        'playstationvita' => 'PSVita',
        'psvita' => 'PSVita',
        'vita' => 'PSVita',
        'playstationportable' => 'PSP',
        'psp' => 'PSP',
        'xboxseriesx/s' => 'XSX',
        'xboxseriesx|s' => 'XSX',
        'xboxseriesx' => 'XSX',
        'xboxseries' => 'XSX',
        'seriesx' => 'XSX',
        'xsx' => 'XSX',
        'xboxone' => 'XOne',
        'xbox360' => 'X360',
        'xbox' => 'Xbox',
        'nintendoswitch' => 'Switch',
        'switch' => 'Switch',
        'wiiu' => 'WiiU',
        'wii' => 'Wii',
        'nintendogamecube' => 'GameCube',
        'gamecube' => 'GameCube',
        'ngc' => 'GameCube',
        'gc' => 'GameCube',
        'nintendo64' => 'N64',
        'n64' => 'N64',
        'nintendo3ds' => '3DS',
        '3ds' => '3DS',
        'nintendods' => 'DS',
        'nds' => 'DS',
        'ds' => 'DS',
        'gameboyadvance' => 'GBA',
        'gba' => 'GBA',
        'gameboycolor' => 'GB',
        'gbc' => 'GB',
        'gameboy' => 'GB',
        'gb' => 'GB',
        'pc(microsoftwindows)' => 'PC',
        'pcwindows' => 'PC',
        'microsoftwindows' => 'PC',
        'windows' => 'PC',
        'win' => 'PC',
        'pc' => 'PC',
        'macintosh' => 'Mac',
        'macos' => 'Mac',
        'mac' => 'Mac',
        'linux' => 'Linux',
        'android' => 'Android',
        'iphone' => 'iOS',
        'ipad' => 'iOS',
        'ios' => 'iOS',
        'pc(microsoft windows)' => 'PC',
        'pc(windows)' => 'PC',
    ];

    /** Libellés lisibles additionnels (imports, anciennes saisies) */
    private const EXTRA_LABELS_BY_CODE = [
        'PS5' => ['PlayStation 5', 'Playstation 5'],
        'PS4' => ['PlayStation 4', 'Playstation 4'],
        'PS3' => ['PlayStation 3'],
        'PS2' => ['PlayStation 2'],
        'PS1' => ['PlayStation', 'PSX'],
        'PSVita' => ['PS Vita', 'PlayStation Vita'],
        'PSP' => ['PlayStation Portable'],
        'XSX' => ['Xbox Series X', 'Xbox Series X|S', 'Xbox Series X/S', 'Series X'],
        'XOne' => ['Xbox One'],
        'X360' => ['Xbox 360'],
        'Switch' => ['Nintendo Switch'],
        'WiiU' => ['Wii U'],
        'GameCube' => ['Nintendo GameCube', 'NGC'],
        'N64' => ['Nintendo 64'],
        '3DS' => ['Nintendo 3DS'],
        'DS' => ['Nintendo DS'],
        'GBA' => ['Game Boy Advance'],
        'GB' => ['Game Boy', 'Game Boy Color'],
        'PC' => ['PC (Microsoft Windows)', 'PC (Windows)', 'Windows'],
        'Mac' => ['Macintosh'],
        'Linux' => [],
        'Android' => [],
        'iOS' => [],
    ];

    /**
     * @return array<string, list<string>> code console → liste de libellés à insérer comme alias distincts
     */
    public static function aliasesGroupedByConsoleCode(): array
    {
        $byCode = [];
        foreach (self::SLUG_OR_LABEL_TO_CODE as $libelle => $code) {
            $byCode[$code][] = $libelle;
        }
        foreach (self::EXTRA_LABELS_BY_CODE as $code => $labels) {
            foreach ($labels as $lbl) {
                $byCode[$code][] = $lbl;
            }
        }
        foreach ($byCode as $code => $labels) {
            $byCode[$code] = array_values(array_unique($labels));
        }

        return $byCode;
    }
}
