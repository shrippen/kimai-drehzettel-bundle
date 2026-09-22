<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\ComplianceIssue;

final class ComplianceWarning
{
    public function __construct(
        public readonly ComplianceIssue $issue,
        public readonly \DateTimeImmutable $date,
        public readonly int $minutes,
        public readonly int $limitMinutes,
    ) {
    }
}
