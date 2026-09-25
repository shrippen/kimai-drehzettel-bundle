<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

// One continuous shooting day = one Kimai timesheet entry.
final class DayInput
{
    /**
     * @param ?DayCategory $nextCategory category of the calendar day after begin, for a day past
     *        midnight without a category override (TZ 5.6.1/5.6.3 split); null otherwise
     */
    public function __construct(
        public readonly \DateTimeImmutable $begin,
        public readonly \DateTimeImmutable $end,
        public readonly DayCategory $category = DayCategory::WORKDAY,
        public readonly DayType $type = DayType::WORKDAY,
        public readonly Catering $catering = Catering::NO,
        public readonly ?int $breakMinutes = null,
        public readonly ?int $productionDay = null,
        public readonly ?string $note = null,
        public readonly int $extraPayCents = 0,
        public readonly ?int $shootingDayNumber = null,
        public readonly ?DayCategory $nextCategory = null,
    ) {
    }
}
