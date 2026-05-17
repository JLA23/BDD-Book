<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute game_console.igdb_platform_id (filtre API IGDB), valeur par défaut pour les consoles connues.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['game_console'])) {
            return;
        }

        $gameConsole = $schemaManager->introspectTable('game_console');
        if (!$gameConsole->hasColumn('igdb_platform_id')) {
            $this->addSql('ALTER TABLE game_console ADD igdb_platform_id INT DEFAULT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console (igdb_platform_id)');
        }

        $map = [
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
            'PC' => 6,
            'Mac' => 14,
            'Linux' => 3,
            'Android' => 34,
            'iOS' => 39,
        ];
        foreach ($map as $code => $igdbId) {
            $this->addSql('UPDATE game_console SET igdb_platform_id = ? WHERE code = ?', [$igdbId, $code]);
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['game_console'])) {
            return;
        }

        $gameConsole = $schemaManager->introspectTable('game_console');
        if (!$gameConsole->hasColumn('igdb_platform_id')) {
            return;
        }

        $this->addSql('DROP INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console');
        $this->addSql('ALTER TABLE game_console DROP COLUMN igdb_platform_id');
    }
}
