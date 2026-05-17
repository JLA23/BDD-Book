<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chemins locaux stored_path pour images (URL source conservée)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE livre ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE dvd ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE musique ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE kiosk_collec ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE kiosk_num ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE dvd_user_collection ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE musique_user_collection ADD stored_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE lien_user_game ADD stored_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE livre DROP stored_path');
        $this->addSql('ALTER TABLE dvd DROP stored_path');
        $this->addSql('ALTER TABLE musique DROP stored_path');
        $this->addSql('ALTER TABLE game DROP stored_path');
        $this->addSql('ALTER TABLE kiosk_collec DROP stored_path');
        $this->addSql('ALTER TABLE kiosk_num DROP stored_path');
        $this->addSql('ALTER TABLE dvd_user_collection DROP stored_path');
        $this->addSql('ALTER TABLE musique_user_collection DROP stored_path');
        $this->addSql('ALTER TABLE lien_user_game DROP stored_path');
    }
}
