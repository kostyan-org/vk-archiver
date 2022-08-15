<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220815190551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT NOT NULL, post_id INT NOT NULL, owner_id INT NOT NULL, from_id INT NOT NULL, date DATETIME NOT NULL, text LONGTEXT DEFAULT NULL, reply_user_id INT DEFAULT NULL, reply_comment_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_9474526C7E3C61F978CED90B (owner_id, from_id), INDEX IDX_9474526C7E3C61F94B89032C (owner_id, post_id), PRIMARY KEY(id, post_id, owner_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `group` (id INT NOT NULL, name VARCHAR(255) NOT NULL, is_closed TINYINT(1) NOT NULL, deactivated VARCHAR(255) DEFAULT NULL, type VARCHAR(255) NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `like` (type VARCHAR(255) NOT NULL, owner_id INT NOT NULL, item_id INT NOT NULL, user_id INT NOT NULL, deleted_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_AC6340B37E3C61F9A76ED395 (owner_id, user_id), INDEX IDX_AC6340B3A76ED395126F525E (user_id, item_id), INDEX IDX_AC6340B37E3C61F9126F525E (owner_id, item_id), PRIMARY KEY(type, owner_id, item_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, deactivated VARCHAR(255) DEFAULT NULL, is_can TINYINT(1) NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wall (id INT NOT NULL, owner_id INT NOT NULL, from_id INT NOT NULL, date DATETIME NOT NULL, text LONGTEXT DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_13F5EFF678CED90B7E3C61F9 (from_id, owner_id), INDEX IDX_13F5EFF6BF3967507E3C61F9 (id, owner_id), PRIMARY KEY(id, owner_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE `like`');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE wall');
    }
}
