<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414121106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables module jeux vidéo ; ignorée si game existe déjà.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['game'])) {
            $this->write('Skipping Version20260414121106: game table already exists.');

            return;
        }

        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, console VARCHAR(100) NOT NULL, annee INT DEFAULT NULL, editeur VARCHAR(255) DEFAULT NULL, developpeur VARCHAR(255) DEFAULT NULL, classification VARCHAR(50) DEFAULT NULL, genre VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, external_id VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_image (id INT AUTO_INCREMENT NOT NULL, game_id INT DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, position INT NOT NULL, source VARCHAR(50) NOT NULL, INDEX IDX_F70E7DD0E48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_game (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, game_id INT NOT NULL, type_edition VARCHAR(20) NOT NULL, nom_edition VARCHAR(100) DEFAULT NULL, prix_achat NUMERIC(10, 2) DEFAULT NULL, date_achat DATE DEFAULT NULL, store VARCHAR(100) DEFAULT NULL, image_perso VARCHAR(500) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_BB1AA57DA76ED395 (user_id), INDEX IDX_BB1AA57DE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game_image ADD CONSTRAINT FK_F70E7DD0E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien_user_game ADD CONSTRAINT FK_BB1AA57DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_game ADD CONSTRAINT FK_BB1AA57DE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Non fiable si up() a été ignoré.
    }
}
