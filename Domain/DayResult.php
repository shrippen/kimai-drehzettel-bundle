<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;

final class DayResult
{
    /**
     * @param list<Share> $dailyShares one per daily tier
     * @param int $countedMinutes regular minutes that count toward the weekly base
     * @param ?int $amountCents null without pay terms; excludes weekly surcharges
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
    ) {
    }
}
