<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Rounding;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\Tier;
use KimaiPlugin\DrehzettelBundle\Enum\BreakRule;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;

/*
 * Rows come from PDFs exported by the TimeSheet app (tests/fixtures).
 * The app's cent amounts may differ by 1 cent on exact half-cent ties
 * (e.g. 578.125 shown as 578.12). Everything else must match exactly.
 */

const HOUR = 60;
const CENT_TOLERANCE = 1;

function appLike(array $dailyTiers): Ruleset
{
    $base = Rulesets::timesheetApp();

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
    'weekly_gage' => [Rulesets::timesheetApp(), new PayTerms(PayKind::WEEKLY, 158100, 950)],
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

foreach ($setups as $project => [$rules, $terms]) {
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

        $result = weekCalc()->calc($inputs, $rules, $terms);
        foreach ($week['rows'] as $i => $row) {
            $day = $result->days[$i];
            $name = "$project {$row['date']}";
            check("$name work", $row['workMinutes'], $day->workMinutes);
            check("$name tiers", $row['tierMinutes'], shareMinutes($day->dailyShares));
            check("$name night", $row['nightMinutes'], $day->nightMinutes);

            if ($terms === null || $row['cents'] === null) {
                continue;
            }
            $diff = abs($row['cents'] - $day->amountCents);
            check("$name cents within tolerance", true, $diff <= CENT_TOLERANCE);
            $diff === 0 ? $exact++ : $off++;
        }
        check("$project {$week['file']} no weekly pool", 0, $result->weeklyPoolMinutes);
    }
}

echo "reference amounts: $exact exact, $off within 1 cent\n";
