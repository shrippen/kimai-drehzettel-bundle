<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;

$tv = Rulesets::tvFfs2024();
$app = Rulesets::quarterHour();

// 08:00-20:45, break 45: gross 12:45, work 12:00 -> 11th and 12th hour.
$r = dayCalc()->calc(shift('2025-06-16', '08:00', '20:45', 45), $tv, null);
check('tv work 12:00', 720, $r->workMinutes);
check('tv tiers hour up', [60, 60], shareMinutes($r->dailyShares));

// 11:45 work: 1:00 at 25 %, 0:45 at 50 %. TV FFS rounds begun hours up.
$r = dayCalc()->calc(shift('2025-06-16', '08:00', '20:30', 45), $tv, null);
check('tv 11:45 work', 705, $r->workMinutes);
check('tv 11:45 tiers hour up', [60, 60], shareMinutes($r->dailyShares));
$r = dayCalc()->calc(shift('2025-06-16', '08:00', '20:30', 45), $app, null);
check('app 11:45 tiers exact', [60, 45, 0], shareMinutes($r->dailyShares));

// TV FFS 5.8.2: only 45 min of break are unpaid. 75 min break -> 30 min count as work.
$r = dayCalc()->calc(shift('2025-06-16', '10:00', '20:00', 75), $tv, null);
check('tv break excess is work', 555, $r->workMinutes);
$r = dayCalc()->calc(shift('2025-06-16', '10:00', '20:00', 75), $app, null);
check('app break deducted fully', 525, $r->workMinutes);

// Night: 07:30-00:00 crosses 22:00-24:00.
$r = dayCalc()->calc(shift('2024-08-15', '07:30', '00:00', 45), $app, null);
check('night across midnight', 120, $r->nightMinutes);
$r = dayCalc()->calc(shift('2025-06-16', '13:30', '22:15', 45), $app, null);
check('night 22:15 end', 15, $r->nightMinutes);
$r = dayCalc()->calc(shift('2025-06-16', '04:00', '12:00', 45), $app, null);
check('night early morning', 120, $r->nightMinutes);

// Pay: weekly gage 1581 EUR, 50 h -> 31.62 EUR/h.
$terms = new PayTerms(PayKind::WEEKLY, 158100, 950);
$r = dayCalc()->calc(shift('2025-05-19', '08:30', '17:30', 0), $app, $terms);
check('pay 9:00 h', 28458, $r->amountCents);
$r = dayCalc()->calc(shift('2025-05-21', '11:15', '17:00', 45), $app, $terms);
check('pay 5:00 h weekly gage', 15810, $r->amountCents);

// Sunday surcharge sits on the day rate: 1581 / 5 = 316.20, +75 % = 237.15. Day 7 of the week: no staggered shoot.
$r = dayCalc()->calc(shift('2025-05-25', '08:00', '16:45', 45, category: DayCategory::SUNDAY), $tv, $terms, 7);
check('sunday work 8:00', 480, $r->workMinutes);
check('sunday pay', 25296 + 23715, $r->amountCents);

// Saturday surcharge sits on the hourly rate: 8 h * 31.62 * 25 % = 63.24.
$r = dayCalc()->calc(shift('2025-05-24', '08:00', '16:45', 45, category: DayCategory::SATURDAY), $tv, $terms);
check('saturday pay', 25296 + 6324, $r->amountCents);

// A negative stored break must never add work time: 08:30-20:15, break -300 -> work 11:45, break 0.
$r = dayCalc()->calc(shift('2025-05-20', '08:30', '20:15', -300), $app, null);
check('negative break adds nothing', [0, 705], [$r->breakMinutes, $r->workMinutes]);

// DST: night minutes are real minutes of 22:00-06:00 wall clock.
// Fall back (26.10.2025): 22:00-06:00 lasts 9 h. Spring forward (30.3.2025): 7 h.
$r = dayCalc()->calc(shift('2025-10-25', '20:00', '06:00', 0), $app, null);
check('night fall back dst', 540, $r->nightMinutes);
$r = dayCalc()->calc(shift('2025-03-29', '20:00', '06:00', 0), $app, null);
check('night spring forward dst', 420, $r->nightMinutes);
$r = dayCalc()->calc(shift('2025-10-26', '01:00', '07:00', 0), $app, null);
check('night starts inside window on dst day', 360, $r->nightMinutes);

// Extra pay (Zusatzgage/Spesen) is added to the day as is, after the catering deduction:
// 9:00 h = 284.58, catering -9.50, extra +50.00 -> 325.08.
$extra = new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2025-05-19', '08:30'), at('2025-05-19', '17:30'), catering: KimaiPlugin\DrehzettelBundle\Enum\Catering::YES, breakMinutes: 0, extraPayCents: 5000);
$r = dayCalc()->calc($extra, $app, $terms);
check('extra pay added to day', [32508, 5000], [$r->amountCents, $r->extraPayCents]);
$r = dayCalc()->calc($extra, $app, null);
check('extra pay without terms', [null, 5000], [$r->amountCents, $r->extraPayCents]);
$travel = new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2025-05-19', '08:30'), at('2025-05-19', '17:30'), type: KimaiPlugin\DrehzettelBundle\Enum\DayType::TRAVEL, breakMinutes: 0, extraPayCents: 1234);
check('extra pay on travel day', 28458 + 1234, dayCalc()->calc($travel, $app, $terms)->amountCents);
$week = weekCalc()->calc([$extra], $app, $terms);
check('extra pay in week total', 32508, $week->totalCents);
