<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Rounding;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\Streak;
use KimaiPlugin\DrehzettelBundle\Domain\Tier;
use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/*
 * Rows come from exported reference timesheets (tests/fixtures).
 * The source's cent amounts may differ by 1 cent on exact half-cent ties
 * (e.g. 578.125 shown as 578.12). Everything else must match exactly.
 */

const HOUR = 60;
const CENT_TOLERANCE = 1;

function appLike(array $dailyTiers): Ruleset
{
    $base = Rulesets::quarterHour();

    return new Ruleset(
        name: 'fixture',
        defaultBreakMinutes: 45,
        breakRule: BreakRule::DEDUCT_ALL,
        freeBreakMinutes: 0,
        workRounding: new Rounding(RoundingUnit::QUARTER, RoundingMode::NEAREST),
        surchargeRounding: new Rounding(RoundingUnit::MINUTE, RoundingMode::NEAREST),
        dailyTiers: $dailyTiers,
        weeklyTiers: $base->weeklyTiers,
        weeklyGageHours: 50,
        dailyGageHours: 8,
        nightFromMinute: 22 * HOUR,
        nightToMinute: 6 * HOUR,
        nightBasisPoints: 2500,
        categorySurcharges: $base->categorySurcharges,
        sixthDayBasisPoints: 2500,
        seventhDayBasisPoints: 5000,
    );
}

$setups = [
    'weekly_gage' => [Rulesets::quarterHour(), new PayTerms(PayKind::WEEKLY, 158100, 950)],
    'daily_gage' => [
        appLike([new Tier(8 * HOUR, 0), new Tier(10 * HOUR, 2500), new Tier(13 * HOUR, 10000)]),
        new PayTerms(PayKind::DAILY, 40000),
    ],
    'custom_tiers' => [
        appLike([new Tier(10 * HOUR, 0), new Tier(12 * HOUR, 6000), new Tier(13 * HOUR, 10000)]),
        null,
    ],
];

$fixtures = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/reference_days.json'), true, flags: JSON_THROW_ON_ERROR);
$exact = 0;
$off = 0;

// Both streak modes give the same figures: no fixture week reaches a 6th day either way.
foreach ([StreakMode::CALENDAR_WEEK, StreakMode::CONSECUTIVE] as $mode) {
    foreach ($setups as $project => [$rules, $terms]) {
        $rules = withMode($rules, $mode);
        // Weeks carry the day-in-a-row count, as FilmWeekService::period() does.
        $last = null;
        foreach ($fixtures[$project]['weeks'] as $week) {
            $inputs = [];
            foreach ($week['rows'] as $row) {
                $inputs[] = shift(
                    $row['date'],
                    $row['begin'],
                    $row['end'],
                    $row['breakMinutes'],
                    $row['catering'] ? Catering::YES : Catering::NO,
                );
            }

            $result = weekCalc()->calc($inputs, $rules, $terms, $last === null ? 0 : Streak::carry($last, $inputs[0]));
            $last = $result->days[count($result->days) - 1];
            foreach ($week['rows'] as $i => $row) {
                $day = $result->days[$i];
                $name = "$project {$mode->value} {$row['date']}";
                check("$name work", $row['workMinutes'], $day->workMinutes);
                check("$name tiers", $row['tierMinutes'], shareMinutes($day->dailyShares));
                check("$name night", $row['nightMinutes'], $day->nightMinutes);
                if (isset($row['underMinutes'])) {
                    check("$name under-time", $row['underMinutes'], $day->underMinutes);
                }

                if ($terms === null || $row['cents'] === null) {
                    continue;
                }
                $diff = abs($row['cents'] - $day->amountCents);
                check("$name cents within tolerance", true, $diff <= CENT_TOLERANCE);
                $diff === 0 ? $exact++ : $off++;
            }
            check("$project {$mode->value} {$week['file']} no weekly pool", 0, $result->weeklyPoolMinutes);
        }
    }
}

echo "reference amounts: $exact exact, $off within 1 cent\n";
