<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DrehzettelBundle: restrict an engagement to specific Kimai activities';
    }

    public function up(Schema $schema): void
    {
        $engagement = $schema->getTable('kimai2_ext_drehzettel_engagement');
        if (!$engagement->hasColumn('activity_ids')) {
            $engagement->addColumn('activity_ids', 'json', ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('kimai2_ext_drehzettel_engagement')->dropColumn('activity_ids');
    }
}
