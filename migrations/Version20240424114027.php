<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240424114027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE eglise_utilisateur (eglise_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_20D4A98D62B480E8 (eglise_id), INDEX IDX_20D4A98DFB88E14F (utilisateur_id), PRIMARY KEY(eglise_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE eglise_utilisateur ADD CONSTRAINT FK_20D4A98D62B480E8 FOREIGN KEY (eglise_id) REFERENCES eglise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE eglise_utilisateur ADD CONSTRAINT FK_20D4A98DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE diocese ADD responsable_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE diocese ADD CONSTRAINT FK_8849E74253C59D72 FOREIGN KEY (responsable_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_8849E74253C59D72 ON diocese (responsable_id)');
        $this->addSql('ALTER TABLE eglise ADD paroisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE eglise ADD CONSTRAINT FK_CDD593E1C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('CREATE INDEX IDX_CDD593E1C40C2240 ON eglise (paroisse_id)');
        $this->addSql('ALTER TABLE feuillet ADD utilisateur_id INT DEFAULT NULL, ADD eglise_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE feuillet ADD CONSTRAINT FK_1A3ACC61FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE feuillet ADD CONSTRAINT FK_1A3ACC6162B480E8 FOREIGN KEY (eglise_id) REFERENCES eglise (id)');
        $this->addSql('CREATE INDEX IDX_1A3ACC61FB88E14F ON feuillet (utilisateur_id)');
        $this->addSql('CREATE INDEX IDX_1A3ACC6162B480E8 ON feuillet (eglise_id)');
        $this->addSql('ALTER TABLE paroisse ADD diocese_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE paroisse ADD CONSTRAINT FK_9068949CB600009 FOREIGN KEY (diocese_id) REFERENCES diocese (id)');
        $this->addSql('CREATE INDEX IDX_9068949CB600009 ON paroisse (diocese_id)');
        $this->addSql('ALTER TABLE utilisateur ADD paroisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3C40C2240 FOREIGN KEY (paroisse_id) REFERENCES paroisse (id)');
        $this->addSql('CREATE INDEX IDX_1D1C63B3C40C2240 ON utilisateur (paroisse_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE eglise_utilisateur DROP FOREIGN KEY FK_20D4A98D62B480E8');
        $this->addSql('ALTER TABLE eglise_utilisateur DROP FOREIGN KEY FK_20D4A98DFB88E14F');
        $this->addSql('DROP TABLE eglise_utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3C40C2240');
        $this->addSql('DROP INDEX IDX_1D1C63B3C40C2240 ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP paroisse_id');
        $this->addSql('ALTER TABLE diocese DROP FOREIGN KEY FK_8849E74253C59D72');
        $this->addSql('DROP INDEX IDX_8849E74253C59D72 ON diocese');
        $this->addSql('ALTER TABLE diocese DROP responsable_id');
        $this->addSql('ALTER TABLE eglise DROP FOREIGN KEY FK_CDD593E1C40C2240');
        $this->addSql('DROP INDEX IDX_CDD593E1C40C2240 ON eglise');
        $this->addSql('ALTER TABLE eglise DROP paroisse_id');
        $this->addSql('ALTER TABLE feuillet DROP FOREIGN KEY FK_1A3ACC61FB88E14F');
        $this->addSql('ALTER TABLE feuillet DROP FOREIGN KEY FK_1A3ACC6162B480E8');
        $this->addSql('DROP INDEX IDX_1A3ACC61FB88E14F ON feuillet');
        $this->addSql('DROP INDEX IDX_1A3ACC6162B480E8 ON feuillet');
        $this->addSql('ALTER TABLE feuillet DROP utilisateur_id, DROP eglise_id');
        $this->addSql('ALTER TABLE paroisse DROP FOREIGN KEY FK_9068949CB600009');
        $this->addSql('DROP INDEX IDX_9068949CB600009 ON paroisse');
        $this->addSql('ALTER TABLE paroisse DROP diocese_id');
    }
}
