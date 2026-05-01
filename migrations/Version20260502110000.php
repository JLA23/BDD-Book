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
        $this->addSql('ALTER TABLE game_console ADD igdb_platform_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console (igdb_platform_id)');

        $map = [
            'PS5' => 167,
            'PS4' => 48,
            'PS3' => 16,
            'PS2' => 9,
            'PS1' => 7,
            'PSVita' => 46,
            'PSP' => 38,
            'XSX' => 169,
            'XOne' => 12,
            'X360' => 11,
            'Xbox' => 1,
            'Switch' => 130,
            'WiiU' => 41,
            'Wii' => 5,
            'GameCube' => 21,
            'N64' => 4,
            '3DS' => 18,
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
        $this->addSql('DROP INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console');
        $this->addSql('ALTER TABLE game_console DROP COLUMN igdb_platform_id');
    }
}
