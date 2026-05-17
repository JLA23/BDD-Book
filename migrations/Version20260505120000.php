<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute dvd.edition (libellé édition commerciale DVDFr).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['dvd'])) {
            return;
        }

        $table = $schemaManager->introspectTable('dvd');
        if (!$table->hasColumn('edition')) {
            $this->addSql('ALTER TABLE dvd ADD edition VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['dvd'])) {
            return;
        }

        $table = $schemaManager->introspectTable('dvd');
        if ($table->hasColumn('edition')) {
            $this->addSql('ALTER TABLE dvd DROP COLUMN edition');
        }
    }
}
