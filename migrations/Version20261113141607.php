<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113141607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

   public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_reservation ADD purchase_order_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE inventory_reservation ADD CONSTRAINT FK_363A759AA45D7E6A FOREIGN KEY (purchase_order_id) REFERENCES `order` (id)');

        $this->addSql('CREATE INDEX IDX_363A759AA45D7E6A ON inventory_reservation (purchase_order_id)');
    }

  public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_reservation DROP FOREIGN KEY FK_363A759AA45D7E6A');
        $this->addSql('DROP INDEX IDX_363A759AA45D7E6A ON inventory_reservation');
        $this->addSql('ALTER TABLE inventory_reservation DROP purchase_order_id');
    }
}
