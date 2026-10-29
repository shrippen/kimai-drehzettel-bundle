<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261029000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_drehzettel_mail_log';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: log of sent timesheet mails';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $table = $schema->createTable(self::TABLE);
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('engagement_id', 'integer', ['notnull' => true]);
        $table->addColumn('attempted_at', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('slot', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('mail_trigger', 'string', ['length' => 16, 'notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 16, 'notnull' => true]);
        $table->addColumn('recipient', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('period_from', 'date_immutable', ['notnull' => true]);
        $table->addColumn('period_to', 'date_immutable', ['notnull' => true]);
        $table->addColumn('attempts', 'smallint', ['notnull' => true, 'default' => 1]);
        $table->addColumn('error', 'string', ['length' => 255, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['engagement_id', 'slot'], 'idx_drehzettel_mail_log_slot');
        $table->addForeignKeyConstraint('kimai2_ext_drehzettel_engagement', ['engagement_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_MAIL_LOG_ENGAGEMENT');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable(self::TABLE);
    }
}
