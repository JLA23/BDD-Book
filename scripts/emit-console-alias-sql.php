<?php

declare(strict_types=1);

/**
 * Émet sur stdout les INSERT game_console_alias alignés sur ConsoleSlugAliasSeedData.
 * Usage : php scripts/emit-console-alias-sql.php >> migrations/game_entities_migration.sql (après édition manuelle)
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$by = App\Data\ConsoleSlugAliasSeedData::aliasesGroupedByConsoleCode();

foreach ($by as $code => $labels) {
    foreach ($labels as $libelle) {
        $libelleEsc = str_replace(["\\", "'"], ["\\\\", "\\'"], $libelle);
        $codeEsc = str_replace(["\\", "'"], ["\\\\", "\\'"], $code);
        echo "INSERT INTO game_console_alias (libelle, console_id)\n";
        echo "SELECT '{$libelleEsc}', id FROM game_console WHERE code = '{$codeEsc}' LIMIT 1\n";
        echo "ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);\n";
    }
}
