<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute prix_achat et date_achat sur lien_kiosk_num_user (propriétaire magazine).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_kiosk_num_user'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_kiosk_num_user');
        if (!$table->hasColumn('prix_achat')) {
            $this->addSql('ALTER TABLE lien_kiosk_num_user ADD prix_achat NUMERIC(10, 2) DEFAULT NULL');
        }
        if (!$table->hasColumn('date_achat')) {
            $this->addSql('ALTER TABLE lien_kiosk_num_user ADD date_achat DATE DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['lien_kiosk_num_user'])) {
            return;
        }

        $table = $schemaManager->introspectTable('lien_kiosk_num_user');
        if ($table->hasColumn('date_achat')) {
            $this->addSql('ALTER TABLE lien_kiosk_num_user DROP COLUMN date_achat');
        }
        if ($table->hasColumn('prix_achat')) {
            $this->addSql('ALTER TABLE lien_kiosk_num_user DROP COLUMN prix_achat');
        }
    }
}
