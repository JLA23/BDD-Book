<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute brick_set.ean (code-barres EAN/UPC).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['brick_set'])) {
            return;
        }

        $table = $schemaManager->introspectTable('brick_set');
        if (!$table->hasColumn('ean')) {
            $this->addSql('ALTER TABLE brick_set ADD ean VARCHAR(20) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['brick_set'])) {
            return;
        }

        $table = $schemaManager->introspectTable('brick_set');
        if ($table->hasColumn('ean')) {
            $this->addSql('ALTER TABLE brick_set DROP COLUMN ean');
        }
    }
}
