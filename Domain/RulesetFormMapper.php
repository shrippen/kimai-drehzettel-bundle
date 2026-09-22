<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

/**
 * Ruleset <-> flat form data in the units people think in:
 * hours instead of minutes, percent instead of basis points.
 */
final class RulesetFormMapper
{
    public const DAILY_TIER_SLOTS = 4;
    public const WEEKLY_TIER_SLOTS = 3;

    private const CATEGORIES = [DayCategory::SATURDAY, DayCategory::SUNDAY, DayCategory::HOLIDAY];

    /**
     * @return array<string, mixed>
     */
    public static function toForm(Ruleset $rules): array
    {
        $data = [
            'name' => $rules->name,
            'defaultBreakMinutes' => $rules->defaultBreakMinutes,
            'breakRule' => $rules->breakRule->value,
            'freeBreakMinutes' => $rules->freeBreakMinutes,
            'workRoundingUnit' => $rules->workRounding->unit->value,
            'workRoundingMode' => $rules->workRounding->mode->value,
            'surchargeRoundingUnit' => $rules->surchargeRounding->unit->value,
            'surchargeRoundingMode' => $rules->surchargeRounding->mode->value,
            'weeklyGageHours' => $rules->weeklyGageHours,
            'dailyGageHours' => $rules->dailyGageHours,
            'minDayHours' => self::hours($rules->minDayMinutes),
            'nightFrom' => self::clock($rules->nightFromMinute),
            'nightTo' => self::clock($rules->nightToMinute),
            'nightPercent' => self::percent($rules->nightBasisPoints),
            'sixthDayPercent' => $rules->sixthDayBasisPoints === null ? null : self::percent($rules->sixthDayBasisPoints),
            'seventhDayPercent' => $rules->seventhDayBasisPoints === null ? null : self::percent($rules->seventhDayBasisPoints),
        ];

        foreach (self::slots($rules->dailyTiers, self::DAILY_TIER_SLOTS) as $i => $tier) {
            $data['dailyTier' . ($i + 1) . 'After'] = $tier === null ? null : self::hours($tier->afterMinutes);
            $data['dailyTier' . ($i + 1) . 'Percent'] = $tier === null ? null : self::percent($tier->basisPoints);
        }
        foreach (self::slots($rules->weeklyTiers, self::WEEKLY_TIER_SLOTS) as $i => $tier) {
            $data['weeklyTier' . ($i + 1) . 'After'] = $tier === null ? null : self::hours($tier->afterMinutes);
            $data['weeklyTier' . ($i + 1) . 'Percent'] = $tier === null ? null : self::percent($tier->basisPoints);
        }

        foreach (self::CATEGORIES as $category) {
            $surcharge = $rules->surchargeFor($category);
            $data[$category->value . 'Percent'] = $surcharge === null ? null : self::percent($surcharge->basisPoints);
            $data[$category->value . 'Basis'] = ($surcharge?->basis ?? SurchargeBasis::HOURLY)->value;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromForm(array $data): Ruleset
    {
        $categories = [];
        foreach (self::CATEGORIES as $category) {
            $percent = $data[$category->value . 'Percent'] ?? null;
            if ($percent === null || $percent === '') {
                continue;
            }
            $categories[$category->value] = new CategorySurcharge(
                self::basisPoints($percent),
                SurchargeBasis::from($data[$category->value . 'Basis'] ?? SurchargeBasis::HOURLY->value),
            );
        }

        return new Ruleset(
            name: (string) ($data['name'] ?? ''),
            defaultBreakMinutes: (int) $data['defaultBreakMinutes'],
            breakRule: BreakRule::from($data['breakRule']),
            freeBreakMinutes: (int) $data['freeBreakMinutes'],
            workRounding: new Rounding(RoundingUnit::from((int) $data['workRoundingUnit']), RoundingMode::from($data['workRoundingMode'])),
            surchargeRounding: new Rounding(RoundingUnit::from((int) $data['surchargeRoundingUnit']), RoundingMode::from($data['surchargeRoundingMode'])),
            dailyTiers: self::tiers($data, 'dailyTier', self::DAILY_TIER_SLOTS),
            weeklyTiers: self::tiers($data, 'weeklyTier', self::WEEKLY_TIER_SLOTS),
            weeklyGageHours: (int) $data['weeklyGageHours'],
            dailyGageHours: (int) $data['dailyGageHours'],
            nightFromMinute: self::minutesOfDay((string) $data['nightFrom']),
            nightToMinute: self::minutesOfDay((string) $data['nightTo']),
            nightBasisPoints: self::basisPoints($data['nightPercent']),
            categorySurcharges: $categories,
            sixthDayBasisPoints: self::optionalBasisPoints($data['sixthDayPercent'] ?? null),
            seventhDayBasisPoints: self::optionalBasisPoints($data['seventhDayPercent'] ?? null),
            minDayMinutes: self::minutes($data['minDayHours'] ?? 8),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<Tier>
     */
    private static function tiers(array $data, string $prefix, int $slots): array
    {
        $tiers = [];
        for ($i = 1; $i <= $slots; ++$i) {
            $after = $data[$prefix . $i . 'After'] ?? null;
            $percent = $data[$prefix . $i . 'Percent'] ?? null;
            if ($after === null || $after === '' || $percent === null || $percent === '') {
                continue;
            }
            $tiers[] = new Tier(self::minutes($after), self::basisPoints($percent));
        }

        return $tiers;
    }

    /**
     * @param list<Tier> $tiers
     * @return list<?Tier> exactly $slots entries, empty slots are null
     */
    private static function slots(array $tiers, int $slots): array
    {
        $padded = [];
        for ($i = 0; $i < $slots; ++$i) {
            $padded[] = $tiers[$i] ?? null;
        }

        return $padded;
    }

    private static function hours(int $minutes): float
    {
        return round($minutes / Units::MINUTES_PER_HOUR, 2);
    }

    private static function minutes(mixed $hours): int
    {
        return (int) round((float) $hours * Units::MINUTES_PER_HOUR);
    }

    private static function percent(int $basisPoints): float
    {
        return round($basisPoints / (Units::BASIS_POINTS / 100), 2);
    }

    private static function basisPoints(mixed $percent): int
    {
        return (int) round((float) $percent * (Units::BASIS_POINTS / 100));
    }

    private static function optionalBasisPoints(mixed $percent): ?int
    {
        return $percent === null || $percent === '' ? null : self::basisPoints($percent);
    }

    // 1320 -> "22:00"
    private static function clock(int $minutes): string
    {
        return Format::hm($minutes);
    }

    // "22:00" -> 1320
    private static function minutesOfDay(string $clock): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $clock) + [0, 0]);

        return $hours * Units::MINUTES_PER_HOUR + $minutes;
    }
}
