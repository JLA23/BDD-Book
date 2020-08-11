<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200803191929 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE livre DROP FOREIGN KEY FK_AC634F9998D3FE22');
        $this->addSql('DROP INDEX IDX_AC634F9998D3FE22 ON livre');
        $this->addSql('ALTER TABLE livre DROP monnaie_id, DROP prixBase');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE livre ADD monnaie_id INT DEFAULT NULL, ADD prixBase DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE livre ADD CONSTRAINT FK_AC634F9998D3FE22 FOREIGN KEY (monnaie_id) REFERENCES monnaie (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_AC634F9998D3FE22 ON livre (monnaie_id)');
    }
}
