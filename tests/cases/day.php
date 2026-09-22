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

// Sunday surcharge sits on the day rate: 1581 / 5 = 316.20, +75 % = 237.15.
$r = dayCalc()->calc(shift('2025-05-25', '08:00', '16:45', 45, category: DayCategory::SUNDAY), $tv, $terms);
check('sunday work 8:00', 480, $r->workMinutes);
check('sunday pay', 25296 + 23715, $r->amountCents);

// Saturday surcharge sits on the hourly rate: 8 h * 31.62 * 25 % = 63.24.
$r = dayCalc()->calc(shift('2025-05-24', '08:00', '16:45', 45, category: DayCategory::SATURDAY), $tv, $terms);
check('saturday pay', 25296 + 6324, $r->amountCents);
