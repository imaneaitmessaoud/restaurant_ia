<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819143317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_categories (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, ordre INT NOT NULL, image VARCHAR(500) DEFAULT NULL, actif TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_items (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(8, 2) NOT NULL, image VARCHAR(500) DEFAULT NULL, disponible TINYINT(1) NOT NULL, ordre INT DEFAULT NULL, ingredients JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', allergenes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', temps_preparation INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_70B2CA2A12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_personalizations (id INT AUTO_INCREMENT NOT NULL, menu_item_id INT NOT NULL, type VARCHAR(255) NOT NULL, options_json JSON NOT NULL COMMENT \'(DC2Type:json)\', obligatoire TINYINT(1) NOT NULL, prix_supplement NUMERIC(8, 2) DEFAULT NULL, ordre INT NOT NULL, actif TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D1E335FE9AB44FE0 (menu_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A12469DE2 FOREIGN KEY (category_id) REFERENCES menu_categories (id)');
        $this->addSql('ALTER TABLE menu_personalizations ADD CONSTRAINT FK_D1E335FE9AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_items (id)');
        $this->addSql('ALTER TABLE commande_items ADD CONSTRAINT FK_D876368E82EA2E54 FOREIGN KEY (commande_id) REFERENCES commandes (id)');
        $this->addSql('ALTER TABLE commande_items ADD CONSTRAINT FK_D876368E9AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_items (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande_items DROP FOREIGN KEY FK_D876368E9AB44FE0');
        $this->addSql('ALTER TABLE menu_items DROP FOREIGN KEY FK_70B2CA2A12469DE2');
        $this->addSql('ALTER TABLE menu_personalizations DROP FOREIGN KEY FK_D1E335FE9AB44FE0');
        $this->addSql('DROP TABLE menu_categories');
        $this->addSql('DROP TABLE menu_items');
        $this->addSql('DROP TABLE menu_personalizations');
        $this->addSql('ALTER TABLE commande_items DROP FOREIGN KEY FK_D876368E82EA2E54');
    }
}
