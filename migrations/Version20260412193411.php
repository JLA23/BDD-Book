<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412193411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auteur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_collection (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_image (id INT AUTO_INCREMENT NOT NULL, brick_set_id INT NOT NULL, url VARCHAR(500) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, position INT NOT NULL, source VARCHAR(100) DEFAULT NULL, INDEX IDX_48510A68A8CBC419 (brick_set_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_marque (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, logo VARCHAR(255) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE brick_set (id INT AUTO_INCREMENT NOT NULL, marque_id INT DEFAULT NULL, collection_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, reference VARCHAR(50) NOT NULL, prix DOUBLE PRECISION DEFAULT NULL, annee INT DEFAULT NULL, nb_pieces INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8E7EFAC5AEA34913 (reference), INDEX IDX_8E7EFAC54827B9B2 (marque_id), INDEX IDX_8E7EFAC5514956FD (collection_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, nom LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE collection (id INT AUTO_INCREMENT NOT NULL, nom LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kiosk_collec (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, editeur VARCHAR(255) DEFAULT NULL, debpub DATE DEFAULT NULL, findeb DATE DEFAULT NULL, nbnum INT NOT NULL, statut TINYINT(1) DEFAULT 1 NOT NULL, image LONGBLOB DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, create_date DATE NOT NULL, update_date DATE NOT NULL, createUser INT NOT NULL, updateUser INT NOT NULL, INDEX IDX_D15C6B1D7F41C459 (createUser), INDEX IDX_D15C6B1DB7AD1DEE (updateUser), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kiosk_num (id INT AUTO_INCREMENT NOT NULL, num INT NOT NULL, couverture LONGBLOB DEFAULT NULL, ean VARCHAR(50) DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, date_parution DATE DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, create_date DATE NOT NULL, update_date DATE NOT NULL, idKioskCollec INT NOT NULL, idMonnaie INT DEFAULT NULL, createUser INT NOT NULL, updateUser INT NOT NULL, INDEX IDX_595DDCF033CBFDEA (idKioskCollec), INDEX IDX_595DDCF0A31F8914 (idMonnaie), INDEX IDX_595DDCF07F41C459 (createUser), INDEX IDX_595DDCF0B7AD1DEE (updateUser), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_auteur_livre (id INT AUTO_INCREMENT NOT NULL, livre_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_E27EDB137D925CB (livre_id), INDEX IDX_E27EDB160BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_kiosk_num_user (id INT AUTO_INCREMENT NOT NULL, commentaire LONGTEXT DEFAULT NULL, idUser INT NOT NULL, idKioskNum INT NOT NULL, INDEX IDX_20BAA69FFE6E88D7 (idUser), INDEX IDX_20BAA69F159627E7 (idKioskNum), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_brick_set (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, brick_set_id INT NOT NULL, monnaie_id INT DEFAULT NULL, date_achat DATETIME DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, est_monte TINYINT(1) NOT NULL, est_complet TINYINT(1) NOT NULL, INDEX IDX_55397A6EA76ED395 (user_id), INDEX IDX_55397A6EA8CBC419 (brick_set_id), INDEX IDX_55397A6E98D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lien_user_livre (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, livre_id INT NOT NULL, monnaie_id INT DEFAULT NULL, note INT DEFAULT NULL, dateAchat DATETIME DEFAULT NULL, prix_achat DOUBLE PRECISION DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, particularite LONGTEXT DEFAULT NULL, seq_access INT DEFAULT NULL, INDEX IDX_6641BC87A76ED395 (user_id), INDEX IDX_6641BC8737D925CB (livre_id), INDEX IDX_6641BC8798D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE livre (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, collection_id INT DEFAULT NULL, edition_id INT DEFAULT NULL, monnaie_id INT DEFAULT NULL, titre VARCHAR(255) NOT NULL, isbn VARCHAR(255) DEFAULT NULL, numero INT DEFAULT NULL, annee INT DEFAULT NULL, cycle VARCHAR(255) DEFAULT NULL, tome INT DEFAULT NULL, pages INT DEFAULT NULL, prixBase DOUBLE PRECISION DEFAULT NULL, cote INT DEFAULT NULL, amazon LONGTEXT DEFAULT NULL, poids DOUBLE PRECISION DEFAULT NULL, resume LONGTEXT DEFAULT NULL, image LONGBLOB DEFAULT NULL, image_2 VARCHAR(255) DEFAULT NULL, INDEX IDX_AC634F9912469DE2 (category_id), INDEX IDX_AC634F99514956FD (collection_id), INDEX IDX_AC634F9974281A5E (edition_id), INDEX IDX_AC634F9998D3FE22 (monnaie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE monnaie (id INT AUTO_INCREMENT NOT NULL, symbole VARCHAR(10) NOT NULL, libelle VARCHAR(255) NOT NULL, diminutif VARCHAR(5) NOT NULL, parDefault TINYINT(1) NOT NULL, valeur DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, id_access VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE brick_image ADD CONSTRAINT FK_48510A68A8CBC419 FOREIGN KEY (brick_set_id) REFERENCES brick_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE brick_set ADD CONSTRAINT FK_8E7EFAC54827B9B2 FOREIGN KEY (marque_id) REFERENCES brick_marque (id)');
        $this->addSql('ALTER TABLE brick_set ADD CONSTRAINT FK_8E7EFAC5514956FD FOREIGN KEY (collection_id) REFERENCES brick_collection (id)');
        $this->addSql('ALTER TABLE kiosk_collec ADD CONSTRAINT FK_D15C6B1D7F41C459 FOREIGN KEY (createUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE kiosk_collec ADD CONSTRAINT FK_D15C6B1DB7AD1DEE FOREIGN KEY (updateUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF033CBFDEA FOREIGN KEY (idKioskCollec) REFERENCES kiosk_collec (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF0A31F8914 FOREIGN KEY (idMonnaie) REFERENCES monnaie (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF07F41C459 FOREIGN KEY (createUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF0B7AD1DEE FOREIGN KEY (updateUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_auteur_livre ADD CONSTRAINT FK_E27EDB137D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE lien_auteur_livre ADD CONSTRAINT FK_E27EDB160BB6FE6 FOREIGN KEY (auteur_id) REFERENCES auteur (id)');
        $this->addSql('ALTER TABLE lien_kiosk_num_user ADD CONSTRAINT FK_20BAA69FFE6E88D7 FOREIGN KEY (idUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_kiosk_num_user ADD CONSTRAINT FK_20BAA69F159627E7 FOREIGN KEY (idKioskNum) REFERENCES kiosk_num (id)');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6EA8CBC419 FOREIGN KEY (brick_set_id) REFERENCES brick_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lien_user_brick_set ADD CONSTRAINT FK_55397A6E98D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC87A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC8737D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE lien_user_livre ADD CONSTRAINT FK_6641BC8798D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9912469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F99514956FD FOREIGN KEY (collection_id) REFERENCES collection (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9974281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9998D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE brick_image DROP FOREIGN KEY FK_48510A68A8CBC419');
        $this->addSql('ALTER TABLE brick_set DROP FOREIGN KEY FK_8E7EFAC54827B9B2');
        $this->addSql('ALTER TABLE brick_set DROP FOREIGN KEY FK_8E7EFAC5514956FD');
        $this->addSql('ALTER TABLE kiosk_collec DROP FOREIGN KEY FK_D15C6B1D7F41C459');
        $this->addSql('ALTER TABLE kiosk_collec DROP FOREIGN KEY FK_D15C6B1DB7AD1DEE');
        $this->addSql('ALTER TABLE kiosk_num DROP FOREIGN KEY FK_595DDCF033CBFDEA');
        $this->addSql('ALTER TABLE kiosk_num DROP FOREIGN KEY FK_595DDCF0A31F8914');
        $this->addSql('ALTER TABLE kiosk_num DROP FOREIGN KEY FK_595DDCF07F41C459');
        $this->addSql('ALTER TABLE kiosk_num DROP FOREIGN KEY FK_595DDCF0B7AD1DEE');
        $this->addSql('ALTER TABLE lien_auteur_livre DROP FOREIGN KEY FK_E27EDB137D925CB');
        $this->addSql('ALTER TABLE lien_auteur_livre DROP FOREIGN KEY FK_E27EDB160BB6FE6');
        $this->addSql('ALTER TABLE lien_kiosk_num_user DROP FOREIGN KEY FK_20BAA69FFE6E88D7');
        $this->addSql('ALTER TABLE lien_kiosk_num_user DROP FOREIGN KEY FK_20BAA69F159627E7');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6EA76ED395');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6EA8CBC419');
        $this->addSql('ALTER TABLE lien_user_brick_set DROP FOREIGN KEY FK_55397A6E98D3FE22');
        $this->addSql('ALTER TABLE lien_user_livre DROP FOREIGN KEY FK_6641BC87A76ED395');
        $this->addSql('ALTER TABLE lien_user_livre DROP FOREIGN KEY FK_6641BC8737D925CB');
        $this->addSql('ALTER TABLE lien_user_livre DROP FOREIGN KEY FK_6641BC8798D3FE22');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9912469DE2');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F99514956FD');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9974281A5E');
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9998D3FE22');
        $this->addSql('DROP TABLE auteur');
        $this->addSql('DROP TABLE brick_collection');
        $this->addSql('DROP TABLE brick_image');
        $this->addSql('DROP TABLE brick_marque');
        $this->addSql('DROP TABLE brick_set');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE collection');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE kiosk_collec');
        $this->addSql('DROP TABLE kiosk_num');
        $this->addSql('DROP TABLE lien_auteur_livre');
        $this->addSql('DROP TABLE lien_kiosk_num_user');
        $this->addSql('DROP TABLE lien_user_brick_set');
        $this->addSql('DROP TABLE lien_user_livre');
        $this->addSql('DROP TABLE livre');
        $this->addSql('DROP TABLE monnaie');
        $this->addSql('DROP TABLE user');
    }
}
