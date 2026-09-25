<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260927000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_drehzettel_engagement';
    private const COLUMN = 'azv';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: AZV credit per engagement (TV FFS TZ 6), null = automatic';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if (!$table->hasColumn(self::COLUMN)) {
            $table->addColumn(self::COLUMN, 'boolean', ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable(self::TABLE)->dropColumn(self::COLUMN);
    }
}
