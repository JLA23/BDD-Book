<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419080951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonne user.sections_enregistrement si absente.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $table = $schemaManager->introspectTable('user');
        if ($table->hasColumn('sections_enregistrement')) {
            return;
        }

        $this->addSql('ALTER TABLE user ADD sections_enregistrement JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $table = $schemaManager->introspectTable('user');
        if (!$table->hasColumn('sections_enregistrement')) {
            return;
        }

        $this->addSql('ALTER TABLE user DROP sections_enregistrement');
    }
}
