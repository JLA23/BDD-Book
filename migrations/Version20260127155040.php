<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127155040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kiosk_num ADD date_parution DATE DEFAULT NULL, ADD idMonnaie INT DEFAULT NULL');
        $this->addSql('ALTER TABLE kiosk_num ADD CONSTRAINT FK_595DDCF0A31F8914 FOREIGN KEY (idMonnaie) REFERENCES monnaie (id)');
        $this->addSql('CREATE INDEX IDX_595DDCF0A31F8914 ON kiosk_num (idMonnaie)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kiosk_num DROP FOREIGN KEY FK_595DDCF0A31F8914');
        $this->addSql('DROP INDEX IDX_595DDCF0A31F8914 ON kiosk_num');
        $this->addSql('ALTER TABLE kiosk_num DROP date_parution, DROP idMonnaie');
    }
}
