<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260924000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DrehzettelBundle: remembered mail recipient per engagement';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_drehzettel_mail_recipient')) {
            $table = $schema->createTable('kimai2_ext_drehzettel_mail_recipient');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('engagement_id', 'integer', ['notnull' => true]);
            $table->addColumn('email', 'string', ['length' => 255, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['engagement_id'], 'uniq_drehzettel_mail_engagement');
            $table->addForeignKeyConstraint('kimai2_ext_drehzettel_engagement', ['engagement_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_MAIL_ENGAGEMENT');
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('kimai2_ext_drehzettel_mail_recipient');
    }
}
