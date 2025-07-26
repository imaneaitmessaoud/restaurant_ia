<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250721223039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, customer_phone VARCHAR(20) NOT NULL, preferred_categories JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', favorite_dishes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', last_order_date DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_A0A0B4D1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_preference ADD CONSTRAINT FK_A0A0B4D1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_preference DROP FOREIGN KEY FK_A0A0B4D1A76ED395');
        $this->addSql('DROP TABLE customer_preference');
    }
}
