<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028103647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD full_name VARCHAR(150) NOT NULL, ADD email VARCHAR(180) NOT NULL, ADD phone VARCHAR(30) NOT NULL, ADD address VARCHAR(255) NOT NULL, ADD city VARCHAR(100) NOT NULL, ADD zip_code VARCHAR(20) NOT NULL, ADD payment_method VARCHAR(30) NOT NULL, ADD created_at DATETIME NOT NULL, CHANGE user_id user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP full_name, DROP email, DROP phone, DROP address, DROP city, DROP zip_code, DROP payment_method, DROP created_at, CHANGE user_id user_id INT NOT NULL');
    }
}
