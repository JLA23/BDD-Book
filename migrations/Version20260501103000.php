<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée game_console_alias (mapping libellé brut → game_console).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_console_alias (id INT AUTO_INCREMENT NOT NULL, console_id INT NOT NULL, libelle VARCHAR(150) NOT NULL, INDEX IDX_5435A2CD72F9DD9F (console_id), UNIQUE INDEX uniq_game_console_alias_libelle (libelle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game_console_alias ADD CONSTRAINT FK_5435A2CD72F9DD9F FOREIGN KEY (console_id) REFERENCES game_console (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_console_alias DROP FOREIGN KEY FK_5435A2CD72F9DD9F');
        $this->addSql('DROP TABLE game_console_alias');
    }
}
