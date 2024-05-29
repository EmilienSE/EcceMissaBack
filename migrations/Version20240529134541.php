<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240529134541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuillet ADD paroisse_id INT NOT NULL, ADD celebration_date DATE NOT NULL');
        $this->addSql('ALTER TABLE feuillet ADD CONSTRAINT FK_1A3ACC61C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('CREATE INDEX IDX_1A3ACC61C40C2240 ON feuillet (paroisse_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feuillet DROP FOREIGN KEY FK_1A3ACC61C40C2240');
        $this->addSql('DROP INDEX IDX_1A3ACC61C40C2240 ON feuillet');
        $this->addSql('ALTER TABLE feuillet DROP paroisse_id, DROP celebration_date');
    }
}
