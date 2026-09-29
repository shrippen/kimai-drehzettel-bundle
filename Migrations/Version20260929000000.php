<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260929000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_drehzettel_mail_recipient';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: mail template and automatic mail schedule per engagement';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if ($table->hasColumn('rhythm')) {
            return;
        }
        $table->addColumn('subject', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('body', 'text', ['notnull' => false]);
        $table->addColumn('rhythm', 'string', ['length' => 16, 'default' => 'off']);
        $table->addColumn('weekday', 'smallint', ['default' => 1]);
        $table->addColumn('hour', 'smallint', ['default' => 7]);
        $table->addColumn('last_slot', 'string', ['length' => 32, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        foreach (['subject', 'body', 'rhythm', 'weekday', 'hour', 'last_slot'] as $column) {
            $table->dropColumn($column);
        }
    }
}
