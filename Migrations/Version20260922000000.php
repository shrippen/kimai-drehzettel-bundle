<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260922000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DrehzettelBundle initial schema: rulesets, engagements, film days';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_drehzettel_ruleset')) {
            $table = $schema->createTable('kimai2_ext_drehzettel_ruleset');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('rules', 'json', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name'], 'uniq_drehzettel_ruleset_name');
        }

        if (!$schema->hasTable('kimai2_ext_drehzettel_engagement')) {
            $table = $schema->createTable('kimai2_ext_drehzettel_engagement');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('project_id', 'integer', ['notnull' => true]);
            $table->addColumn('role', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('pay_kind', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('gage_cents', 'integer', ['notnull' => true]);
            $table->addColumn('catering_deduction_cents', 'integer', ['notnull' => true]);
            $table->addColumn('valid_from', 'date_immutable', ['notnull' => true]);
            $table->addColumn('valid_to', 'date_immutable', ['notnull' => false]);
            $table->addColumn('ruleset_name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('rules', 'json', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id', 'project_id'], 'idx_drehzettel_engagement_user_project');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_ENGAGEMENT_USER');
            $table->addForeignKeyConstraint('kimai2_projects', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_ENGAGEMENT_PROJECT');
        }

        if (!$schema->hasTable('kimai2_ext_drehzettel_day')) {
            $table = $schema->createTable('kimai2_ext_drehzettel_day');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('engagement_id', 'integer', ['notnull' => true]);
            $table->addColumn('day_date', 'date_immutable', ['notnull' => true]);
            $table->addColumn('break_minutes', 'integer', ['notnull' => false]);
            $table->addColumn('catering', 'string', ['length' => 8, 'notnull' => true]);
            $table->addColumn('category', 'string', ['length' => 16, 'notnull' => false]);
            $table->addColumn('day_type', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('production_day', 'smallint', ['notnull' => false]);
            $table->addColumn('note', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['engagement_id', 'day_date'], 'uniq_drehzettel_day_engagement_date');
            $table->addForeignKeyConstraint('kimai2_ext_drehzettel_engagement', ['engagement_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_DAY_ENGAGEMENT');
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('kimai2_ext_drehzettel_day');
        $schema->dropTable('kimai2_ext_drehzettel_engagement');
        $schema->dropTable('kimai2_ext_drehzettel_ruleset');
    }
}
