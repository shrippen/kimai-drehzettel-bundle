<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260923000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DrehzettelBundle: PDF options per engagement, signature images';
    }

    public function up(Schema $schema): void
    {
        $engagement = $schema->getTable('kimai2_ext_drehzettel_engagement');
        if (!$engagement->hasColumn('pdf_options')) {
            $engagement->addColumn('pdf_options', 'json', ['notnull' => false]);
        }

        if (!$schema->hasTable('kimai2_ext_drehzettel_signature')) {
            $table = $schema->createTable('kimai2_ext_drehzettel_signature');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('mime', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('data', 'text', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'], 'uniq_drehzettel_signature_user');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_DREHZETTEL_SIGNATURE_USER');
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('kimai2_ext_drehzettel_signature');
        $schema->getTable('kimai2_ext_drehzettel_engagement')->dropColumn('pdf_options');
    }
}
