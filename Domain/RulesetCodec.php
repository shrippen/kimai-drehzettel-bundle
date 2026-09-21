<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;
use KimaiPlugin\DrehzettelBundle\Enum\SurchargeBasis;

/**
 * Ruleset <-> plain array, stored as JSON on the engagement (snapshot).
 * Keys are the contract: renaming one breaks stored engagements.
 */
final class RulesetCodec
{
    private const REQUIRED_KEYS = [
        'name', 'defaultBreakMinutes', 'breakRule', 'freeBreakMinutes', 'workRounding',
        'surchargeRounding', 'dailyTiers', 'weeklyTiers', 'weeklyGageHours', 'dailyGageHours',
        'night', 'categories', 'sixthDayBp', 'seventhDayBp',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Ruleset $rules): array
    {
        $categories = [];
        foreach ($rules->categorySurcharges as $key => $surcharge) {
            $categories[$key] = ['bp' => $surcharge->basisPoints, 'basis' => $surcharge->basis->value];
        }

        return [
            'name' => $rules->name,
            'defaultBreakMinutes' => $rules->defaultBreakMinutes,
            'breakRule' => $rules->breakRule->value,
            'freeBreakMinutes' => $rules->freeBreakMinutes,
            'workRounding' => self::roundingToArray($rules->workRounding),
            'surchargeRounding' => self::roundingToArray($rules->surchargeRounding),
            'dailyTiers' => self::tiersToArray($rules->dailyTiers),
            'weeklyTiers' => self::tiersToArray($rules->weeklyTiers),
            'weeklyGageHours' => $rules->weeklyGageHours,
            'dailyGageHours' => $rules->dailyGageHours,
            'night' => [
                'from' => $rules->nightFromMinute,
                'to' => $rules->nightToMinute,
                'bp' => $rules->nightBasisPoints,
            ],
            'categories' => $categories,
            'sixthDayBp' => $rules->sixthDayBasisPoints,
            'seventhDayBp' => $rules->seventhDayBasisPoints,
            'minDayMinutes' => $rules->minDayMinutes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException on missing keys or unknown enum values
     */
    public static function fromArray(array $data): Ruleset
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Invalid ruleset data: missing '$key'");
            }
        }

        try {
            return self::build($data);
        } catch (\ValueError | \TypeError | \Error $e) {
            throw new \InvalidArgumentException('Invalid ruleset data: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function build(array $data): Ruleset
    {
        $categories = [];
        foreach ($data['categories'] as $key => $item) {
            $categories[$key] = new CategorySurcharge($item['bp'], SurchargeBasis::from($item['basis']));
        }

        return new Ruleset(
            name: $data['name'],
            defaultBreakMinutes: $data['defaultBreakMinutes'],
            breakRule: BreakRule::from($data['breakRule']),
            freeBreakMinutes: $data['freeBreakMinutes'],
            workRounding: self::roundingFromArray($data['workRounding']),
            surchargeRounding: self::roundingFromArray($data['surchargeRounding']),
            dailyTiers: self::tiersFromArray($data['dailyTiers']),
            weeklyTiers: self::tiersFromArray($data['weeklyTiers']),
            weeklyGageHours: $data['weeklyGageHours'],
            dailyGageHours: $data['dailyGageHours'],
            nightFromMinute: $data['night']['from'],
            nightToMinute: $data['night']['to'],
            nightBasisPoints: $data['night']['bp'],
            categorySurcharges: $categories,
            sixthDayBasisPoints: $data['sixthDayBp'],
            seventhDayBasisPoints: $data['seventhDayBp'],
            minDayMinutes: $data['minDayMinutes'] ?? 480,
        );
    }

    /**
     * @return array{unit: int, mode: string}
     */
    private static function roundingToArray(Rounding $rounding): array
    {
        return ['unit' => $rounding->unit->value, 'mode' => $rounding->mode->value];
    }

    /**
     * @param array{unit: int, mode: string} $data
     */
    private static function roundingFromArray(array $data): Rounding
    {
        return new Rounding(RoundingUnit::from($data['unit']), RoundingMode::from($data['mode']));
    }

    /**
     * @param list<Tier> $tiers
     * @return list<array{after: int, bp: int}>
     */
    private static function tiersToArray(array $tiers): array
    {
        return array_map(static fn (Tier $t): array => ['after' => $t->afterMinutes, 'bp' => $t->basisPoints], $tiers);
    }

    /**
     * @param list<array{after: int, bp: int}> $data
     * @return list<Tier>
     */
    private static function tiersFromArray(array $data): array
    {
        return array_map(static fn (array $t): Tier => new Tier($t['after'], $t['bp']), $data);
    }
}
