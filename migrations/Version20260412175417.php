<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412175417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lego_collection (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lego_image (id INT AUTO_INCREMENT NOT NULL, lego_set_id INT NOT NULL, url VARCHAR(500) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, position INT NOT NULL, source VARCHAR(100) DEFAULT NULL, INDEX IDX_67B294AD43EA11B8 (lego_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lego_set (id INT AUTO_INCREMENT NOT NULL, collection_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, reference VARCHAR(50) NOT NULL, prix DOUBLE PRECISION DEFAULT NULL, annee INT DEFAULT NULL, nb_pieces INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_BF40359B514956FD (collection_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_lego_set (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, lego_set_id INT NOT NULL, monnaie_id INT DEFAULT NULL, date_achat DATETIME DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, est_monte TINYINT(1) NOT NULL, est_complet TINYINT(1) NOT NULL, INDEX IDX_430948B1A76ED395 (user_id), INDEX IDX_430948B143EA11B8 (lego_set_id), INDEX IDX_430948B198D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lego_image ADD CONSTRAINT FK_67B294AD43EA11B8 FOREIGN KEY (lego_set_id) REFERENCES lego_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lego_set ADD CONSTRAINT FK_BF40359B514956FD FOREIGN KEY (collection_id) REFERENCES lego_collection (id)');
        $this->addSql('ALTER TABLE lien_user_lego_set ADD CONSTRAINT FK_430948B1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_lego_set ADD CONSTRAINT FK_430948B143EA11B8 FOREIGN KEY (lego_set_id) REFERENCES lego_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien_user_lego_set ADD CONSTRAINT FK_430948B198D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lego_image DROP FOREIGN KEY FK_67B294AD43EA11B8');
        $this->addSql('ALTER TABLE lego_set DROP FOREIGN KEY FK_BF40359B514956FD');
        $this->addSql('ALTER TABLE lien_user_lego_set DROP FOREIGN KEY FK_430948B1A76ED395');
        $this->addSql('ALTER TABLE lien_user_lego_set DROP FOREIGN KEY FK_430948B143EA11B8');
        $this->addSql('ALTER TABLE lien_user_lego_set DROP FOREIGN KEY FK_430948B198D3FE22');
        $this->addSql('DROP TABLE lego_collection');
        $this->addSql('DROP TABLE lego_image');
        $this->addSql('DROP TABLE lego_set');
        $this->addSql('DROP TABLE lien_user_lego_set');
    }
}
