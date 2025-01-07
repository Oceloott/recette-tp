<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20250107160401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'adding field at recipe for image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe ADD image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe DROP image');
    }
}
