<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

// Who and what the timesheet is for. Pure data, no Kimai entities.
final class TimesheetMeta
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $projectName,
        public readonly string $role,
        public readonly string $locale,
        public readonly bool $hasPay,
        public readonly ?string $signatureDataUri = null,
        public readonly string $currency = Format::CURRENCY,
    ) {
    }
}
