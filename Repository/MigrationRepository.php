<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;

/**
 * The plugin migrations that already ran, read from Doctrine's version table.
 */
class MigrationRepository
{
    // Same as table_storage in Migrations/doctrine_migrations.yaml.
    private const TABLE = 'bundle_migration_drehzettel';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Short class names, e.g. "Version20260922000000". Never installed: [].
     *
     * @return list<string>
     */
    public function executed(): array
    {
        try {
            $versions = $this->connection->fetchFirstColumn('SELECT version FROM ' . self::TABLE);
        } catch (TableNotFoundException) {
            return [];
        }

        // "DrehzettelBundle\Migrations\Version20260922000000" -> "Version20260922000000"
        return array_map(static fn (string $version): string => substr($version, strrpos($version, '\\') + 1), $versions);
    }
}
