<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260926000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_drehzettel_day';
    private const COLUMN = 'shooting_day_number';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: running shooting-day number of the production';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if (!$table->hasColumn(self::COLUMN)) {
            $table->addColumn(self::COLUMN, 'integer', ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable(self::TABLE)->dropColumn(self::COLUMN);
    }
}
