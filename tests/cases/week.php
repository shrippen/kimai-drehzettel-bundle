<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

$tv = Rulesets::tvFfs2024();
$app = Rulesets::quarterHour();

// Mon-Fri 10:00 work each = exactly 50 h, no weekly overtime.
$week = [];
foreach (['16', '17', '18', '19', '20'] as $d) {
    $week[] = shift("2025-06-$d", '08:00', '18:45', 45);
}
$r = weekCalc()->calc($week, $tv, null);
check('50 h no pool', 0, $r->weeklyPoolMinutes);
check('50 h work', 3000, $r->workMinutes);

// Saturday 8 h is the 6th day: TV FFS pools it as weekly overtime.
// 5 h at 25 %, 3 h at 50 %.
$saturday = shift('2025-06-21', '08:00', '16:45', 45, category: DayCategory::SATURDAY);
$r = weekCalc()->calc([...$week, $saturday], $tv, null);
check('tv 6th day pool', 480, $r->weeklyPoolMinutes);
check('tv 6th day shares', [300, 180], shareMinutes($r->weeklyShares));

// Same week with the app rules: fixed 25 % on the 6th day, no pool.
$r = weekCalc()->calc([...$week, $saturday], $app, null);
check('app 6th day no pool', 0, $r->weeklyPoolMinutes);
check('app 6th day share', 2500, $r->days[5]->dayCountShare?->basisPoints);

// Regular hours above 50 h: 5 x 11:00 work, daily overtime excluded -> counted 5 x 10:00.
$long = [];
foreach (['16', '17', '18', '19', '20'] as $d) {
    $long[] = shift("2025-06-$d", '08:00', '19:45', 45);
}
$r = weekCalc()->calc($long, $tv, null);
check('daily overtime not in weekly base', 0, $r->weeklyPoolMinutes);

// Weekly pay adds the pooled surcharge of the 6th day.
// 5 h * 31.62 * 25 % + 3 h * 31.62 * 50 % = 86.955 -> 86.96 EUR.
$terms = new PayTerms(PayKind::WEEKLY, 158100, 0);
$r = weekCalc()->calc([...$week, $saturday], $tv, $terms);
check('tv weekly cents', 8696, $r->weeklyCents);

// Errors.
try {
    weekCalc()->calc([shift('2025-06-16', '08:00', '16:00'), shift('2025-06-16', '17:00', '20:00')], $tv, null);
    check('duplicate day throws', true, false);
} catch (InvalidArgumentException) {
    check('duplicate day throws', true, true);
}
try {
    weekCalc()->calc([shift('2025-06-16', '08:00', '16:00'), shift('2025-06-23', '08:00', '16:00')], $tv, null);
    check('two weeks throws', true, false);
} catch (InvalidArgumentException) {
    check('two weeks throws', true, true);
}
