<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Enum\ComplianceIssue;
use KimaiPlugin\DrehzettelBundle\Service\ComplianceChecker;

$tv = Rulesets::tvFfs2024();
$checker = new ComplianceChecker();

function issues(array $warnings): array
{
    return array_map(fn ($w) => $w->issue->value, $warnings);
}

// A clean week: no warnings.
$clean = weekCalc()->calc([
    shift('2026-06-15', '08:00', '18:00', 45),
    shift('2026-06-16', '08:00', '18:00', 45),
], $tv, null);
check('compliance: clean week', [], issues($checker->check($clean)));

// 12:30 gross on one day exceeds the 12 h daily maximum.
$longDay = weekCalc()->calc([shift('2026-06-15', '08:00', '20:30', 0)], $tv, null);
$warnings = $checker->check($longDay);
check('compliance: daily max flagged', [ComplianceIssue::DAILY_MAX->value], issues($warnings));
check('compliance: daily max minutes', 750, $warnings[0]->minutes);

// Exactly 12 h gross is still within the limit.
$exactly12 = weekCalc()->calc([shift('2026-06-15', '08:00', '20:00', 0)], $tv, null);
check('compliance: exactly 12h ok', [], issues($checker->check($exactly12)));

// Six 11 h days push the week over 60 h.
$week = [];
foreach (['15', '16', '17', '18', '19', '20'] as $d) {
    $week[] = shift("2026-06-$d", '08:00', '19:00', 0);
}
$over60 = weekCalc()->calc($week, $tv, null);
check('compliance: weekly max flagged', true, in_array(ComplianceIssue::WEEKLY_MAX->value, issues($checker->check($over60)), true));

// Rest time: day 1 gross 10h (under the 11h trigger), end 23:00, next begin
// 08:00 -> 9h rest, less than the normal 11h requirement.
$shortRest = weekCalc()->calc([
    shift('2026-06-15', '13:00', '23:00', 0),
    shift('2026-06-16', '08:00', '18:00', 0),
], $tv, null);
$warnings = $checker->check($shortRest);
check('compliance: short rest flagged', [ComplianceIssue::REST_TIME->value], issues($warnings));
check('compliance: rest minutes measured', 540, $warnings[0]->minutes);
check('compliance: rest required 11h', 660, $warnings[0]->limitMinutes);

// Exactly 11 h rest is fine after a normal (under 11h) day.
$exactRest = weekCalc()->calc([
    shift('2026-06-15', '13:00', '23:00', 0),
    shift('2026-06-16', '10:00', '18:00', 0),
], $tv, null);
check('compliance: exact 11h rest ok', [], issues($checker->check($exactRest)));

// After a day past the 11th full hour (11:30 gross), required rest grows to
// 11.5 h. 10.5h rest to the next day is now too short.
$extended = weekCalc()->calc([
    shift('2026-06-15', '08:00', '19:30', 0),
    shift('2026-06-16', '06:00', '16:00', 0),
], $tv, null);
$warnings = $checker->check($extended);
check('compliance: extended rest required after long day', [ComplianceIssue::REST_TIME->value], issues($warnings));
check('compliance: extended rest is 11.5h', 690, $warnings[0]->limitMinutes);

// 11.5 h rest exactly satisfies the extended requirement.
$extendedOk = weekCalc()->calc([
    shift('2026-06-15', '08:00', '19:30', 0),
    shift('2026-06-16', '07:00', '17:00', 0),
], $tv, null);
check('compliance: 11.5h rest ok after long day', [], issues($checker->check($extendedOk)));

// Week boundary: last day of the previous week (Sunday) ends 23:00, this week's
// Monday begins 08:00 -> 9h rest, too short. Not visible without $previousDay.
$sundayBefore = dayCalc()->calc(shift('2026-06-21', '13:00', '23:00', 0), $tv, null);
$mondayAfter = weekCalc()->calc([shift('2026-06-22', '08:00', '18:00', 0)], $tv, null);
check('compliance: week boundary rest ignored without previous day', [], issues($checker->check($mondayAfter)));
$warnings = $checker->check($mondayAfter, $sundayBefore);
check('compliance: week boundary rest flagged with previous day', [ComplianceIssue::REST_TIME->value], issues($warnings));
check('compliance: week boundary rest minutes measured', 540, $warnings[0]->minutes);

// Week boundary: 11 h rest across the boundary is fine.
$sundayOk = dayCalc()->calc(shift('2026-06-21', '13:00', '23:00', 0), $tv, null);
$mondayOk = weekCalc()->calc([shift('2026-06-22', '10:00', '18:00', 0)], $tv, null);
check('compliance: week boundary 11h rest ok', [], issues($checker->check($mondayOk, $sundayOk)));

// TZ 5.2.5 / 5.9.1 measure working time: breaks (TZ 5.8.2) and travel (PA FAQ) do not count.
// 12:45 gross with 45 min break is 12:00 net: within the daily maximum; 12:46 gross is 12:01 net.
$net12 = weekCalc()->calc([shift('2026-06-15', '07:00', '19:45', 45)], $tv, null);
check('compliance: 12:00 net ok', [], issues($checker->check($net12)));
$net1201 = $checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '19:46', 45)], $tv, null));
check('compliance: 12:01 net flagged', [ComplianceIssue::DAILY_MAX->value, 721], [$net1201[0]->issue->value, $net1201[0]->minutes]);

// Gross over 12 h, net under: 12:30 gross with 45 min break is 11:45 net, no warning.
check('compliance: gross over 12h net under ok', [], issues($checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '19:30', 45)], $tv, null))));

// Break beyond the free 45 min counts as work (TV FFS break rule): 13:00 gross, 60 min break = 12:15 net.
check('compliance: excess break counts', [ComplianceIssue::DAILY_MAX->value], issues($checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '20:00', 60)], $tv, null))));

// Begun 12th hour: 11:00 net keeps 11 h rest, 11:01 net needs 11.5 h. Both next days start after 11:15 h rest.
$net11 = $checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '18:45', 45), shift('2026-06-16', '06:00', '12:00', 0)], $tv, null));
check('compliance: 11:00 net keeps 11h rest', [], issues($net11));
$net1101 = $checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '18:46', 45), shift('2026-06-16', '06:01', '12:00', 0)], $tv, null));
check('compliance: 11:01 net needs 11.5h rest', [ComplianceIssue::REST_TIME->value, 675, 690], [$net1101[0]->issue->value, $net1101[0]->minutes, $net1101[0]->limitMinutes]);

// Gross 11:45 with 45 min break (11:00 net): the 11.5 h rest is not due.
check('compliance: gross past 11h net not', [], issues($checker->check(weekCalc()->calc([shift('2026-06-15', '07:00', '18:45', 45), shift('2026-06-16', '05:45', '12:00', 0)], $tv, null))));

// Travel is no working time: a 13 h travel day neither exceeds the daily maximum nor extends the rest.
$travel = new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2026-06-15', '06:00'), at('2026-06-15', '19:00'), type: KimaiPlugin\DrehzettelBundle\Enum\DayType::TRAVEL, breakMinutes: 0);
check('compliance: travel day no daily max, 11h rest', [], issues($checker->check(weekCalc()->calc([$travel, shift('2026-06-16', '06:00', '12:00', 0)], $tv, null))));
