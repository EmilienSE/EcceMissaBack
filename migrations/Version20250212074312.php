<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250212074312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feuillet_view (id INT AUTO_INCREMENT NOT NULL, feuillet_id INT NOT NULL, paroisse_id INT NOT NULL, viewed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A2C04E4B8E1725CD (feuillet_id), INDEX IDX_A2C04E4BC40C2240 (paroisse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE feuillet_view ADD CONSTRAINT FK_A2C04E4B8E1725CD FOREIGN KEY (feuillet_id) REFERENCES feuillet (id)');
        $this->addSql('ALTER TABLE feuillet_view ADD CONSTRAINT FK_A2C04E4BC40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuillet_view DROP FOREIGN KEY FK_A2C04E4B8E1725CD');
        $this->addSql('ALTER TABLE feuillet_view DROP FOREIGN KEY FK_A2C04E4BC40C2240');
        $this->addSql('DROP TABLE feuillet_view');
    }
}
