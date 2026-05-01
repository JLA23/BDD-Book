<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les colonnes chaîne lien_user_game (console, type_edition, store) — données portées par console_id, type_edition_id, store_id.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_user_game'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_user_game');
        if ($table->hasColumn('console')) {
            $this->addSql('ALTER TABLE lien_user_game DROP COLUMN console');
        }
        if ($table->hasColumn('type_edition')) {
            $this->addSql('ALTER TABLE lien_user_game DROP COLUMN type_edition');
        }
        if ($table->hasColumn('store')) {
            $this->addSql('ALTER TABLE lien_user_game DROP COLUMN store');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_user_game'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_user_game');
        if (!$table->hasColumn('console')) {
            $this->addSql('ALTER TABLE lien_user_game ADD console VARCHAR(100) DEFAULT NULL');
        }
        if (!$table->hasColumn('type_edition')) {
            $this->addSql('ALTER TABLE lien_user_game ADD type_edition VARCHAR(20) DEFAULT NULL');
        }
        if (!$table->hasColumn('store')) {
            $this->addSql('ALTER TABLE lien_user_game ADD store VARCHAR(100) DEFAULT NULL');
        }
    }
}
