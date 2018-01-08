<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use AppBundle\Entity\Restaurant;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180108200438 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery ADD pickup_time_start TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD pickup_time_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery RENAME COLUMN date TO dropoff_time');

        $this->addSql(sprintf("UPDATE delivery SET pickup_time_end = dropoff_time - '%d minutes'::interval", Restaurant::DELIVERY_DELAY));
        $this->addSql(sprintf("UPDATE delivery SET pickup_time_start = pickup_time_end - '%d minutes'::interval", Restaurant::PREPARATION_DELAY));

        $this->addSql('ALTER TABLE delivery ALTER COLUMN pickup_time_start SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN pickup_time_end SET NOT NULL');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP pickup_time_start');
        $this->addSql('ALTER TABLE delivery DROP pickup_time_end');
        $this->addSql('ALTER TABLE delivery RENAME COLUMN dropoff_time TO date');
    }
}
