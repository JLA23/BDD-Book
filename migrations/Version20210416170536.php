<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416170536 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kiosk_collec (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, editeur VARCHAR(255) DEFAULT NULL, debpub DATE DEFAULT NULL, findeb DATE DEFAULT NULL, nbnum INT NOT NULL, statut TINYINT(1) DEFAULT \'1\' NOT NULL, image LONGBLOB DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, create_date DATE NOT NULL, update_date DATE NOT NULL, createUser INT NOT NULL, updateUser INT NOT NULL, INDEX IDX_D15C6B1D7F41C459 (createUser), INDEX IDX_D15C6B1DB7AD1DEE (updateUser), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kiosk_collec ADD CONSTRAINT FK_D15C6B1D7F41C459 FOREIGN KEY (createUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE kiosk_collec ADD CONSTRAINT FK_D15C6B1DB7AD1DEE FOREIGN KEY (updateUser) REFERENCES user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE kiosk_collec');
    }
}
