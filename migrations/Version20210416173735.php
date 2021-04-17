<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416173735 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lien_kiosk_num_user (id INT AUTO_INCREMENT NOT NULL, commentaire LONGTEXT DEFAULT NULL, idUser INT NOT NULL, idKioskNum INT NOT NULL, INDEX IDX_20BAA69FFE6E88D7 (idUser), INDEX IDX_20BAA69F159627E7 (idKioskNum), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lien_kiosk_num_user ADD CONSTRAINT FK_20BAA69FFE6E88D7 FOREIGN KEY (idUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lien_kiosk_num_user ADD CONSTRAINT FK_20BAA69F159627E7 FOREIGN KEY (idKioskNum) REFERENCES kiosk_num (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE lien_kiosk_num_user');
    }
}
