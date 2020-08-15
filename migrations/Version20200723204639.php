<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200723204639 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auteur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, nom LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE format (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_auteur_livre (id INT AUTO_INCREMENT NOT NULL, livre_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_E27EDB137D925CB (livre_id), INDEX IDX_E27EDB160BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_livre (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, livre_id INT NOT NULL, monnaie_id INT DEFAULT NULL, note INT DEFAULT NULL, dateAchat DATETIME DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_6641BC87A76ED395 (user_id), INDEX IDX_6641BC8737D925CB (livre_id), INDEX IDX_6641BC8798D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE livre (id INT AUTO_INCREMENT NOT NULL, format_id INT DEFAULT NULL, category_id INT DEFAULT NULL, edition_id INT DEFAULT NULL, monnaie_id INT DEFAULT NULL, titre VARCHAR(255) NOT NULL, isbn10 VARCHAR(255) DEFAULT NULL, isbn13 VARCHAR(255) DEFAULT NULL, numero INT DEFAULT NULL, annee INT DEFAULT NULL, cycle VARCHAR(255) DEFAULT NULL, tome INT DEFAULT NULL, pages INT DEFAULT NULL, prixBase DOUBLE PRECISION DEFAULT NULL, cote INT DEFAULT NULL, amazon LONGTEXT DEFAULT NULL, poids DOUBLE PRECISION DEFAULT NULL, resume LONGTEXT DEFAULT NULL, image LONGBLOB DEFAULT NULL, INDEX IDX_AC634F99D629F605 (format_id), INDEX IDX_AC634F9912469DE2 (category_id), INDEX IDX_AC634F9974281A5E (edition_id), INDEX IDX_AC634F9998D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE monnaie (id INT AUTO_INCREMENT NOT NULL, symbole VARCHAR(10) NOT NULL, libelle VARCHAR(255) NOT NULL, diminutif VARCHAR(5) NOT NULL, parDefault TINYINT(1) NOT NULL, valeur DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lien_auteur_livre ADD CONSTRAINT FK_E27EDB137D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE lien_auteur_livre ADD CONSTRAINT FK_E27EDB160BB6FE6 FOREIGN KEY (auteur_id) REFERENCES auteur (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC87A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC8737D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC8798D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F99D629F605 FOREIGN KEY (format_id) REFERENCES format (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9912469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9974281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9998D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lien_auteur_livre DROP FOREIGN KEY FK_E27EDB160BB6FE6');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9912469DE2');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9974281A5E');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F99D629F605');
        $this->addSql('ALTER TABLE lien_auteur_livre DROP FOREIGN KEY FK_E27EDB137D925CB');
        $this->addSql('ALTER TABLE lien_user_livre DROP FOREIGN KEY FK_6641BC8737D925CB');
        $this->addSql('ALTER TABLE lien_user_livre DROP FOREIGN KEY FK_6641BC8798D3FE22');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9998D3FE22');
        $this->addSql('DROP TABLE auteur');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE format');
        $this->addSql('DROP TABLE lien_auteur_livre');
        $this->addSql('DROP TABLE lien_user_livre');
        $this->addSql('DROP TABLE livre');
        $this->addSql('DROP TABLE monnaie');
    }
}
