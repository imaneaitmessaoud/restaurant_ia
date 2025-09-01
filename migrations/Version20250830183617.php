<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250830183617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande_items CHANGE menu_item_id menu_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD api_token VARCHAR(100) DEFAULT NULL, ADD api_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E97BA2F5EB ON users (api_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande_items CHANGE menu_item_id menu_item_id INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_1483A5E97BA2F5EB ON users');
        $this->addSql('ALTER TABLE users DROP api_token, DROP api_token_expires_at');
    }
}
