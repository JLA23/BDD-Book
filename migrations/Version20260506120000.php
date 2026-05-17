<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige les igdb_platform_id erronés (ex. PS3=16 était Amiga, PS3 IGDB=9).
 */
final class Version20260506120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige game_console.igdb_platform_id selon les IDs réels IGDB /v4/platforms.';
    }

    public function up(Schema $schema): void
    {
        // Éviter les collisions sur l’index UNIQUE (ex. PS2=9 et PS3→9).
        $this->addSql("UPDATE game_console SET igdb_platform_id = NULL WHERE code IN ('PS3', 'PS2', 'XOne', 'X360', 'Xbox', '3DS')");

        $map = [
            'PS3' => 9,
            'PS2' => 8,
            'XOne' => 49,
            'X360' => 12,
            'Xbox' => 11,
            '3DS' => 37,
        ];

        foreach ($map as $code => $igdbId) {
            $this->addSql('UPDATE game_console SET igdb_platform_id = ? WHERE code = ?', [$igdbId, $code]);
        }
    }

    public function down(Schema $schema): void
    {
        $legacy = [
            'PS3' => 16,
            'PS2' => 9,
            'XOne' => 12,
            'X360' => 11,
            'Xbox' => 1,
            '3DS' => 18,
        ];

        foreach ($legacy as $code => $igdbId) {
            $this->addSql('UPDATE game_console SET igdb_platform_id = ? WHERE code = ?', [$igdbId, $code]);
        }
    }
}
