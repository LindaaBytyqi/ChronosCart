<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027101138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_reservation ADD flash_sale_event_id INT NOT NULL');
        $this->addSql('ALTER TABLE inventory_reservation ADD CONSTRAINT FK_363A759A4958C610 FOREIGN KEY (flash_sale_event_id) REFERENCES flash_sale_event (id)');
        $this->addSql('CREATE INDEX IDX_363A759A4958C610 ON inventory_reservation (flash_sale_event_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventory_reservation DROP FOREIGN KEY FK_363A759A4958C610');
        $this->addSql('DROP INDEX IDX_363A759A4958C610 ON inventory_reservation');
        $this->addSql('ALTER TABLE inventory_reservation DROP flash_sale_event_id');
    }
}
