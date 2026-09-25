<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260925000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_drehzettel_day';
    private const COLUMN = 'extra_pay_cents';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: extra pay (Zusatzgage/Spesen) per film day';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if (!$table->hasColumn(self::COLUMN)) {
            $table->addColumn(self::COLUMN, 'integer', ['notnull' => true, 'default' => 0]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable(self::TABLE)->dropColumn(self::COLUMN);
    }
}
