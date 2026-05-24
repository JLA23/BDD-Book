<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419163406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Briques + jeux : ignorée si les tables existent déjà (doublon Version20260412193411 / schéma manuel).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['brick_collection', 'game'])) {
            $this->write('Skipping Version20260419163406: brick/game tables already exist.');

            return;
        }

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brick_collection (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_image (id INT AUTO_INCREMENT NOT NULL, brick_set_id INT NOT NULL, url VARCHAR(500) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, position INT NOT NULL, source VARCHAR(100) DEFAULT NULL, INDEX IDX_48510A68A8CBC419 (brick_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_marque (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, logo VARCHAR(255) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_set (id INT AUTO_INCREMENT NOT NULL, marque_id INT DEFAULT NULL, collection_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, reference VARCHAR(50) NOT NULL, prix DOUBLE PRECISION DEFAULT NULL, annee INT DEFAULT NULL, nb_pieces INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8E7EFAC5AEA34913 (reference), INDEX IDX_8E7EFAC54827B9B2 (marque_id), INDEX IDX_8E7EFAC5514956FD (collection_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, console VARCHAR(100) NOT NULL, annee INT DEFAULT NULL, editeur VARCHAR(255) DEFAULT NULL, developpeur VARCHAR(255) DEFAULT NULL, classification VARCHAR(50) DEFAULT NULL, genre VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, external_id VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_image (id INT AUTO_INCREMENT NOT NULL, game_id INT DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, position INT NOT NULL, source VARCHAR(50) NOT NULL, INDEX IDX_F70E7DD0E48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_brick_set (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, brick_set_id INT NOT NULL, monnaie_id INT DEFAULT NULL, date_achat DATETIME DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_55397A6EA76ED395 (user_id), INDEX IDX_55397A6EA8CBC419 (brick_set_id), INDEX IDX_55397A6E98D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_game (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, game_id INT NOT NULL, type_edition VARCHAR(20) NOT NULL, nom_edition VARCHAR(100) DEFAULT NULL, prix_achat NUMERIC(10, 2) DEFAULT NULL, date_achat DATE DEFAULT NULL, store VARCHAR(100) DEFAULT NULL, image_perso VARCHAR(500) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_BB1AA57DA76ED395 (user_id), INDEX IDX_BB1AA57DE48FD905 (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE brick_image ADD CONSTRAINT FK_48510A68A8CBC419 FOREIGN KEY (brick_set_id) REFERENCES brick_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE brick_set ADD CONSTRAINT FK_8E7EFAC54827B9B2 FOREIGN KEY (marque_id) REFERENCES brick_marque (id)');
        $this->addSql('ALTER TABLE brick_set ADD CONSTRAINT FK_8E7EFAC5514956FD FOREIGN KEY (collection_id) REFERENCES brick_collection (id)');
        $this->addSql('ALTER TABLE game_image ADD CONSTRAINT FK_F70E7DD0E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6EA8CBC419 FOREIGN KEY (brick_set_id) REFERENCES brick_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6E98D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
        $this->addSql('ALTER TABLE lien_user_game ADD CONSTRAINT FK_BB1AA57DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_game ADD CONSTRAINT FK_BB1AA57DE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Non fiable si up() a été ignoré.
        return;

        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE brick_image DROP FOREIGN KEY FK_48510A68A8CBC419');
        $this->addSql('ALTER TABLE brick_set DROP FOREIGN KEY FK_8E7EFAC54827B9B2');
        $this->addSql('ALTER TABLE brick_set DROP FOREIGN KEY FK_8E7EFAC5514956FD');
        $this->addSql('ALTER TABLE game_image DROP FOREIGN KEY FK_F70E7DD0E48FD905');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6EA76ED395');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6EA8CBC419');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6E98D3FE22');
        $this->addSql('ALTER TABLE lien_user_game DROP FOREIGN KEY FK_BB1AA57DA76ED395');
        $this->addSql('ALTER TABLE lien_user_game DROP FOREIGN KEY FK_BB1AA57DE48FD905');
        $this->addSql('DROP TABLE brick_collection');
        $this->addSql('DROP TABLE brick_image');
        $this->addSql('DROP TABLE brick_marque');
        $this->addSql('DROP TABLE brick_set');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE game_image');
        $this->addSql('DROP TABLE lien_user_brick_set');
        $this->addSql('DROP TABLE lien_user_game');
    }
}
