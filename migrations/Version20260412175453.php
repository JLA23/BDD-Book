<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412175453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op : doublon accidentel de Version20260412175417 (tables Lego déjà créées par celle-ci).';
    }

    public function up(Schema $schema): void
    {
        // Même schéma que Version20260412175417 ; ne rien faire pour éviter "table already exists".
    }

    public function down(Schema $schema): void
    {
        // No-op : ne pas supprimer les tables gérées par Version20260412175417.
    }
}
