<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260413174253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retrait colonnes lien_user_brick_set si encore présentes.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_user_brick_set'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_user_brick_set');
        if (!$table->hasColumn('est_monte')) {
            return;
        }

        $this->addSql('ALTER TABLE lien_user_brick_set DROP est_monte, DROP est_complet');
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_user_brick_set'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_user_brick_set');
        if ($table->hasColumn('est_monte')) {
            return;
        }

        $this->addSql('ALTER TABLE lien_user_brick_set ADD est_monte TINYINT(1) NOT NULL, ADD est_complet TINYINT(1) NOT NULL');
    }
}
