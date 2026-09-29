<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Repository\MigrationRepository;

/**
 * Whether all plugin migrations ran. Kimai runs them only with the install
 * command (or "kimai.sh update"), not on its own: after a plain plugin update
 * new tables are missing until an administrator runs INSTALL_COMMAND.
 */
class SchemaStatus
{
    public const INSTALL_COMMAND = 'bin/console kimai:bundle:drehzettel:install';

    private const MIGRATIONS = __DIR__ . '/../Migrations/Version*.php';

    private ?bool $current = null;

    public function __construct(private readonly MigrationRepository $migrations)
    {
    }

    // Checked once per request.
    public function isCurrent(): bool
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $available = array_map(static fn (string $file): string => basename($file, '.php'), glob(self::MIGRATIONS) ?: []);

        return $this->current = array_diff($available, $this->migrations->executed()) === [];
    }
}
