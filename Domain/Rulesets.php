<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

final class Rulesets
{
    private const NIGHT_FROM = 22 * Units::MINUTES_PER_HOUR;
    private const NIGHT_TO = 6 * Units::MINUTES_PER_HOUR;
    private const HOUR = Units::MINUTES_PER_HOUR;

    // TV FFS of 12.10.2024, TZ 5.3 to 5.8.
    public static function tvFfs2024(): Ruleset
    {
        return new Ruleset(
            name: 'TV FFS 2024',
            defaultBreakMinutes: 45,
            breakRule: BreakRule::EXCESS_COUNTS_AS_WORK,
            freeBreakMinutes: 45,
            workRounding: new Rounding(RoundingUnit::MINUTE, RoundingMode::NEAREST),
            surchargeRounding: new Rounding(RoundingUnit::HOUR, RoundingMode::UP),
            dailyTiers: [new Tier(10 * self::HOUR, 2500), new Tier(11 * self::HOUR, 5000)],
            weeklyTiers: [new Tier(50 * self::HOUR, 2500), new Tier(55 * self::HOUR, 5000)],
            weeklyGageHours: 50,
            dailyGageHours: 10,
            nightFromMinute: self::NIGHT_FROM,
            nightToMinute: self::NIGHT_TO,
            nightBasisPoints: 2500,
            categorySurcharges: [
                DayCategory::SATURDAY->value => new CategorySurcharge(2500, SurchargeBasis::HOURLY),
                DayCategory::SUNDAY->value => new CategorySurcharge(7500, SurchargeBasis::DAY_RATE),
                DayCategory::HOLIDAY->value => new CategorySurcharge(10000, SurchargeBasis::DAY_RATE),
            ],
            sixthDayBasisPoints: null,
            seventhDayBasisPoints: null,
        );
    }

    // Settings of the TimeSheet app for a weekly-gage project.
    public static function timesheetApp(): Ruleset
    {
        return new Ruleset(
            name: 'Like TimeSheet app',
            defaultBreakMinutes: 45,
            breakRule: BreakRule::DEDUCT_ALL,
            freeBreakMinutes: 0,
            workRounding: new Rounding(RoundingUnit::QUARTER, RoundingMode::NEAREST),
            surchargeRounding: new Rounding(RoundingUnit::MINUTE, RoundingMode::NEAREST),
            dailyTiers: [
                new Tier(10 * self::HOUR, 2500),
                new Tier(11 * self::HOUR, 5000),
                new Tier(13 * self::HOUR, 10000),
            ],
            weeklyTiers: [new Tier(50 * self::HOUR, 2500), new Tier(55 * self::HOUR, 5000)],
            weeklyGageHours: 50,
            dailyGageHours: 10,
            nightFromMinute: self::NIGHT_FROM,
            nightToMinute: self::NIGHT_TO,
            nightBasisPoints: 2500,
            categorySurcharges: [
                DayCategory::SATURDAY->value => new CategorySurcharge(2500, SurchargeBasis::HOURLY),
                DayCategory::SUNDAY->value => new CategorySurcharge(7500, SurchargeBasis::DAY_RATE),
                DayCategory::HOLIDAY->value => new CategorySurcharge(10000, SurchargeBasis::DAY_RATE),
            ],
            sixthDayBasisPoints: 2500,
            seventhDayBasisPoints: 5000,
        );
    }
}
