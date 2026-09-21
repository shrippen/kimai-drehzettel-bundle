<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

final class WeekResult
{
    /**
     * @param list<DayResult> $days
     * @param list<Share> $weeklyShares one per weekly tier
     */
    public function __construct(
        public readonly array $days,
        public readonly array $weeklyShares,
        public readonly int $weeklyPoolMinutes,
        public readonly int $workMinutes,
        public readonly int $nightMinutes,
        public readonly ?int $weeklyCents,
        public readonly ?int $totalCents,
    ) {
    }
}
