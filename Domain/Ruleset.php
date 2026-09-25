<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;

final class Ruleset
{
    /** @var list<Tier> */
    public readonly array $dailyTiers;

    /** @var list<Tier> */
    public readonly array $weeklyTiers;

    /**
     * @param list<Tier> $dailyTiers
     * @param list<Tier> $weeklyTiers first tier's afterMinutes is the weekly base (TV FFS: 50 h)
     * @param array<string, CategorySurcharge> $categorySurcharges keyed by DayCategory value
     * @param ?int $sixthDayBasisPoints fixed surcharge on 6th day; null pools it as weekly overtime
     * @param ?int $seventhDayBasisPoints fixed surcharge on 7th day; null pools it as weekly overtime
     * @param int $minDayMinutes shorter days count as under-time (TV FFS 5.3.1: a begun day counts 8 h)
     */
    public function __construct(
        public readonly string $name,
        public readonly int $defaultBreakMinutes,
        public readonly BreakRule $breakRule,
        public readonly int $freeBreakMinutes,
        public readonly Rounding $workRounding,
        public readonly Rounding $surchargeRounding,
        array $dailyTiers,
        array $weeklyTiers,
        public readonly int $weeklyGageHours,
        public readonly int $dailyGageHours,
        public readonly int $nightFromMinute,
        public readonly int $nightToMinute,
        public readonly int $nightBasisPoints,
        public readonly array $categorySurcharges,
        public readonly ?int $sixthDayBasisPoints,
        public readonly ?int $seventhDayBasisPoints,
        public readonly int $minDayMinutes = 480,
    ) {
        $this->dailyTiers = self::sorted($dailyTiers);
        $this->weeklyTiers = self::sorted($weeklyTiers);
    }

    public function surchargeFor(DayCategory $category): ?CategorySurcharge
    {
        return $this->categorySurcharges[$category->value] ?? null;
    }

    /**
     * @param list<Tier> $tiers
     * @return list<Tier>
     */
    private static function sorted(array $tiers): array
    {
        usort($tiers, static fn (Tier $a, Tier $b): int => $a->afterMinutes <=> $b->afterMinutes);

        return $tiers;
    }
}
