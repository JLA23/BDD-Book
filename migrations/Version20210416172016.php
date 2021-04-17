<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416172016 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kiosk_num (id INT AUTO_INCREMENT NOT NULL, num INT NOT NULL, couverture LONGBLOB DEFAULT NULL, ean VARCHAR(50) DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, create_date DATE NOT NULL, update_date DATE NOT NULL, idKioskCollec INT NOT NULL, createUser INT NOT NULL, updateUser INT NOT NULL, INDEX IDX_595DDCF033CBFDEA (idKioskCollec), INDEX IDX_595DDCF07F41C459 (createUser), INDEX IDX_595DDCF0B7AD1DEE (updateUser), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF033CBFDEA FOREIGN KEY (idKioskCollec) REFERENCES kiosk_collec (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF07F41C459 FOREIGN KEY (createUser) REFERENCES user (id)');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF0B7AD1DEE FOREIGN KEY (updateUser) REFERENCES user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE kiosk_num');
    }
}
