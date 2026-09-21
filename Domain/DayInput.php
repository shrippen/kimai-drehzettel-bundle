<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

// One continuous shooting day = one Kimai timesheet entry.
final class DayInput
{
    public function __construct(
        public readonly \DateTimeImmutable $begin,
        public readonly \DateTimeImmutable $end,
        public readonly DayCategory $category = DayCategory::WORKDAY,
        public readonly DayType $type = DayType::WORKDAY,
        public readonly Catering $catering = Catering::NO,
        public readonly ?int $breakMinutes = null,
        public readonly ?int $productionDay = null,
        public readonly ?string $note = null,
    ) {
    }
}
