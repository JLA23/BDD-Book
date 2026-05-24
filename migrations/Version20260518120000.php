<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518120000 extends AbstractMigration
{
    private const TABLES = [
        'livre',
        'dvd',
        'musique',
        'game',
        'kiosk_collec',
        'kiosk_num',
        'dvd_user_collection',
        'musique_user_collection',
        'lien_user_game',
    ];

    public function getDescription(): string
    {
        return 'Chemins locaux stored_path pour images (URL source conservée)';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        foreach (self::TABLES as $tableName) {
            if (!$schemaManager->tablesExist([$tableName])) {
                continue;
            }

            $table = $schemaManager->introspectTable($tableName);
            if ($table->hasColumn('stored_path')) {
                continue;
            }

            $this->addSql(sprintf(
                'ALTER TABLE %s ADD stored_path VARCHAR(500) DEFAULT NULL',
                $tableName
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        foreach (self::TABLES as $tableName) {
            if (!$schemaManager->tablesExist([$tableName])) {
                continue;
            }

            $table = $schemaManager->introspectTable($tableName);
            if (!$table->hasColumn('stored_path')) {
                continue;
            }

            $this->addSql(sprintf('ALTER TABLE %s DROP stored_path', $tableName));
        }
    }
}
