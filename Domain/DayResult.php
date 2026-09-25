<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

final class DayResult
{
    /**
     * @param list<Share> $dailyShares one per daily tier
     * @param int $countedMinutes regular minutes that count toward the weekly base
     * @param ?int $amountCents null without pay terms; excludes weekly surcharges
     * @param int $underMinutes work time missing to the minimum day of the ruleset
     * @param int $extraPayCents Zusatzgage/Spesen, already included in $amountCents
     * @param ?int $shootingDayNumber running day of the production, informational
     */
    public function __construct(
        public readonly \DateTimeImmutable $begin,
        public readonly \DateTimeImmutable $end,
        public readonly int $grossMinutes,
        public readonly int $breakMinutes,
        public readonly int $workMinutes,
        public readonly array $dailyShares,
        public readonly int $nightMinutes,
        public readonly ?CategorySurcharge $categorySurcharge,
        public readonly ?Share $dayCountShare,
        public readonly int $countedMinutes,
        public readonly int $dayNumber,
        public readonly Catering $catering,
        public readonly ?int $amountCents,
        public readonly DayType $dayType = DayType::WORKDAY,
        public readonly DayCategory $category = DayCategory::WORKDAY,
        public readonly ?string $note = null,
        public readonly int $underMinutes = 0,
        public readonly int $extraPayCents = 0,
        public readonly ?int $shootingDayNumber = null,
    ) {
    }
}
