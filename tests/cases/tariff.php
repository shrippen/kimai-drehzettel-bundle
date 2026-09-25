<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\NightShoot;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\StaggeredShoot;
use KimaiPlugin\DrehzettelBundle\Enum\ComplianceIssue;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Service\ComplianceChecker;

/*
 * Staggered shoot (TV FFS TZ 5.6.3 S. 2) and night shoot day boundary (TZ 5.2.4 S. 2,
 * with the calendar-day split of TZ 5.6.1 / 5.6.3 S. 3).
 */

$tv = Rulesets::tvFfs2024();
$terms = new PayTerms(PayKind::WEEKLY, 158100, 950);
$checker = new ComplianceChecker();

// 8 h days on the given dates, category by weekday or as given: 'YYYY-MM-DD' or [date, category, override].
function tariffWeek(array $days): array
{
    return array_map(static function (string|array $d): DayInput {
        [$date, $category, $override] = is_array($d) ? $d + [1 => null, 2 => null] : [$d, null, null];
        $category ??= match (at($date, '00:00')->format('N')) {
            '6' => DayCategory::SATURDAY,
            '7' => DayCategory::SUNDAY,
            default => DayCategory::WORKDAY,
        };

        return new DayInput(at($date, '08:00'), at($date, '16:45'), $category, breakMinutes: 45, productionDay: $override);
    }, $days);
}

function tariffIssues(array $warnings): array
{
    return array_values(array_map(static fn ($w): string => $w->issue->value, $warnings));
}

// --- Staggered shoot ---

// The four holidays by date; Fronleichnam = Easter Sunday + 60 days (2025-06-19, 2026-06-04).
check('staggered: listed holidays', [true, true, true, true, true], array_map(static fn (string $d): bool => StaggeredShoot::isListedHoliday(new DateTimeImmutable($d)), ['2026-01-06', '2025-06-19', '2026-06-04', '2026-08-15', '2026-11-01']));
check('staggered: other holidays', [false, false, false, false], array_map(static fn (string $d): bool => StaggeredShoot::isListedHoliday(new DateTimeImmutable($d)), ['2026-05-01', '2026-12-25', '2026-06-05', '2026-10-03']));

// Wed 17 - Sun 21 June 2026: Sunday is day 5 -> no Sunday surcharge, Saturday keeps its 25 %.
$wedSun = weekCalc()->calc(tariffWeek(['2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21']), $tv, $terms);
check('staggered: sunday day 5 waived', [5, null, DayCategory::SUNDAY], [$wedSun->days[4]->dayNumber, $wedSun->days[4]->categorySurcharge, $wedSun->days[4]->waivedCategory]);
check('staggered: saturday kept', [2500, null], [$wedSun->days[3]->categorySurcharge?->basisPoints, $wedSun->days[3]->waivedCategory]);
check('staggered: sunday pay without day rate', 25296, $wedSun->days[4]->amountCents);
check('staggered: warning', [ComplianceIssue::STAGGERED_SHOOT->value], tariffIssues($checker->check($wedSun)));

// Mon-Sun: Sunday is day 7 -> 75 % on the day rate.
$monSun = weekCalc()->calc(tariffWeek(['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21']), $tv, $terms);
check('staggered: sunday day 7 paid', [7500, null], [$monSun->days[6]->categorySurcharge?->basisPoints, $monSun->days[6]->waivedCategory]);

// Literal reading: a week with a single Sunday shoot makes it day 1 -> waived.
$onlySunday = weekCalc()->calc(tariffWeek(['2026-06-21']), $tv, $terms);
check('staggered: single sunday waived (literal)', [1, null, DayCategory::SUNDAY], [$onlySunday->days[0]->dayNumber, $onlySunday->days[0]->categorySurcharge, $onlySunday->days[0]->waivedCategory]);

// The surcharge day override counts: Sunday entered as day 6 keeps its surcharge.
$overridden = weekCalc()->calc(tariffWeek([['2026-06-21', null, 6]]), $tv, $terms);
check('staggered: override day 6 paid', 7500, $overridden->days[0]->categorySurcharge?->basisPoints);

// Fronleichnam on Thu 4 June 2026 as day 4 is waived, Christmas on Fri 25 Dec 2026 as day 5 is not.
$corpusChristi = weekCalc()->calc(tariffWeek(['2026-06-01', '2026-06-02', '2026-06-03', ['2026-06-04', DayCategory::HOLIDAY]]), $tv, $terms);
check('staggered: fronleichnam waived', [null, DayCategory::HOLIDAY], [$corpusChristi->days[3]->categorySurcharge, $corpusChristi->days[3]->waivedCategory]);
$christmas = weekCalc()->calc(tariffWeek(['2026-12-21', '2026-12-22', '2026-12-23', '2026-12-24', ['2026-12-25', DayCategory::HOLIDAY]]), $tv, $terms);
check('staggered: christmas kept', [10000, null], [$christmas->days[4]->categorySurcharge?->basisPoints, $christmas->days[4]->waivedCategory]);

// --- Night shoot day boundary ---

$span = static fn (string $date, string $begin, string $end): array => [shift($date, $begin, $end)->begin, shift($date, $begin, $end)->end];
$joinKeys = static fn (array $entries): array => array_map(static fn (array $s): string => $s[0]->format('D H:i') . '-' . $s[1]->format('D H:i'), array_values(NightShoot::byWorkingDay($entries)));

// Sat 18:00-24:00 + Sun 00:00-03:00 is one working day; ending exactly at 04:00 still is.
check('night: joined by 04:00', ['Sat 18:00-Sun 03:00'], $joinKeys([$span('2026-06-21', '00:00', '03:00'), $span('2026-06-20', '18:00', '00:00')]));
check('night: joined at 04:00', ['Sat 18:00-Sun 04:00'], $joinKeys([$span('2026-06-20', '18:00', '00:00'), $span('2026-06-21', '00:30', '04:00')]));

// A later Sunday entry stays a day of its own: Sun 14:00-22:00 does not swallow the night's end.
check('night: joined and next day', ['Sat 18:00-Sun 03:00', 'Sun 14:00-Sun 22:00'], $joinKeys([$span('2026-06-20', '18:00', '00:00'), $span('2026-06-21', '00:00', '03:00'), $span('2026-06-21', '14:00', '22:00')]));

// Entries of one date still make one span.
check('night: same date grouped', ['Mon 08:00-Mon 18:00'], $joinKeys([$span('2026-06-15', '08:00', '12:00'), $span('2026-06-15', '13:00', '18:00')]));

// Past 04:00, or after a day that ended before 22:00, a new working day begins.
check('night: past 04:00 separate', ['Sat 18:00-Sun 00:00', 'Sun 00:00-Sun 05:00'], $joinKeys([$span('2026-06-20', '18:00', '00:00'), $span('2026-06-21', '00:00', '05:00')]));
check('night: no night shoot before', ['Sat 08:00-Sat 17:00', 'Sun 01:00-Sun 03:00'], $joinKeys([$span('2026-06-20', '08:00', '17:00'), $span('2026-06-21', '01:00', '03:00')]));

// Joined, the night shoot is one day: day numbering, rest time and daily maximum follow.
// Fri 12:00-24:00 + Sat 00:00-03:00 (15 h, break 45), then Sat 18:00-24:00.
$apart = [shift('2026-06-19', '12:00', '00:00', 45), shift('2026-06-20', '00:00', '03:00', 0), shift('2026-06-21', '14:00', '22:00', 45)];
$joined = [new DayInput(at('2026-06-19', '12:00'), at('2026-06-20', '03:00'), breakMinutes: 45, nextCategory: DayCategory::SATURDAY), shift('2026-06-21', '14:00', '22:00', 45, category: DayCategory::SUNDAY)];
$apartWeek = weekCalc()->calc($apart, $tv, null);
$joinedWeek = weekCalc()->calc($joined, $tv, null);
check('night: day numbers', [[1, 2, 3], [1, 2]], [array_map(static fn ($d): int => $d->dayNumber, $apartWeek->days), array_map(static fn ($d): int => $d->dayNumber, $joinedWeek->days)]);
check('night: apart has no rest', true, in_array(ComplianceIssue::REST_TIME->value, tariffIssues($checker->check($apartWeek)), true));
check('night: joined rest and daily max', [ComplianceIssue::DAILY_MAX->value, ComplianceIssue::STAGGERED_SHOOT->value], tariffIssues($checker->check($joinedWeek)));
check('night: joined daily max minutes', 855, $checker->check($joinedWeek)[0]->minutes);

// Calendar-day split of the Saturday, Sunday and holiday surcharges (TZ 5.6.1, 5.6.3 S. 3), day 6 so no staggered shoot.
// Fri 22:00-Sat 03:00: Saturday 25 % on the 3 h after midnight.
$friSat = dayCalc()->calc(new DayInput(at('2026-06-19', '22:00'), at('2026-06-20', '03:00'), breakMinutes: 0, nextCategory: DayCategory::SATURDAY), $tv, $terms, 6);
check('split: friday into saturday', [null, [[2500, 180]]], [$friSat->categorySurcharge, array_map(static fn ($s): array => [$s->basisPoints, $s->minutes], $friSat->categoryShares)]);

// Sat 18:00-Sun 03:00, break 45: 495 min work, 330 on Saturday and 165 on Sunday (rounded up to hours).
$satSun = dayCalc()->calc(new DayInput(at('2026-06-20', '18:00'), at('2026-06-21', '03:00'), DayCategory::SATURDAY, breakMinutes: 45, nextCategory: DayCategory::SUNDAY), $tv, $terms, 6);
check('split: sunday up to 4 h pro rata', [null, [[2500, 360], [7500, 180]]], [$satSun->categorySurcharge, array_map(static fn ($s): array => [$s->basisPoints, $s->minutes], $satSun->categoryShares)]);

// Sat 14:00-Sun 05:00, break 45: 285 min on Sunday is over 4 h -> Sunday for the whole day, Saturday pro rata.
$satSunLong = dayCalc()->calc(new DayInput(at('2026-06-20', '14:00'), at('2026-06-21', '05:00'), DayCategory::SATURDAY, breakMinutes: 45, nextCategory: DayCategory::SUNDAY), $tv, $terms, 6);
check('split: sunday over 4 h whole day', [7500, [[2500, 600]]], [$satSunLong->categorySurcharge?->basisPoints, array_map(static fn ($s): array => [$s->basisPoints, $s->minutes], $satSunLong->categoryShares)]);
check('split: night past 04:00 noted', [[ComplianceIssue::NIGHT_CUTOFF->value], 300, 240], (static function () use ($checker, $satSunLong): array {
    $notes = array_values(array_filter($checker->check(new KimaiPlugin\DrehzettelBundle\Domain\WeekResult([$satSunLong], [], 0, 0, 0, null, null)), static fn ($w): bool => $w->issue === ComplianceIssue::NIGHT_CUTOFF));

    return [tariffIssues($notes), $notes[0]->minutes, $notes[0]->limitMinutes];
})());

// Sun 21:00-Mon 03:00: 3 h on the Sunday -> pro rata instead of the whole day rate.
$sunMon = dayCalc()->calc(new DayInput(at('2026-06-21', '21:00'), at('2026-06-22', '03:00'), DayCategory::SUNDAY, breakMinutes: 0, nextCategory: DayCategory::WORKDAY), $tv, $terms, 7);
check('split: sunday evening pro rata', [null, [[7500, 180]]], [$sunMon->categorySurcharge, array_map(static fn ($s): array => [$s->basisPoints, $s->minutes], $sunMon->categoryShares)]);
// 6 h at 31.62 EUR/h, night 5 h +25 %, Sunday 3 h +75 %: (360 + 75 + 135) min * 31.62 / 60, rounded once.
check('split: pro rata pay', 30039, $sunMon->amountCents);

// Staggered shoot on the Sunday part: Sat 18:00-Sun 03:00 as day 1 keeps Saturday only.
$satSunFirst = dayCalc()->calc(new DayInput(at('2026-06-20', '18:00'), at('2026-06-21', '03:00'), DayCategory::SATURDAY, breakMinutes: 45, nextCategory: DayCategory::SUNDAY), $tv, $terms, 1);
check('split: staggered sunday part', [[[2500, 360]], DayCategory::SUNDAY], [array_map(static fn ($s): array => [$s->basisPoints, $s->minutes], $satSunFirst->categoryShares), $satSunFirst->waivedCategory]);

// A category override (nextCategory null) keeps one category for the whole day.
$override = dayCalc()->calc(new DayInput(at('2026-06-20', '18:00'), at('2026-06-21', '03:00'), DayCategory::SATURDAY, breakMinutes: 45), $tv, $terms, 6);
check('split: override whole day', [2500, []], [$override->categorySurcharge?->basisPoints, $override->categoryShares]);

// API day summary: pro-rata shares and the waived category.
$summaryEngagement = new KimaiPlugin\DrehzettelBundle\Entity\Engagement();
$summaryEngagement->setGageCents(158100);
$split = KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of(weekCalc()->calc([new DayInput(at('2026-06-20', '18:00'), at('2026-06-21', '03:00'), DayCategory::SATURDAY, breakMinutes: 45, nextCategory: DayCategory::SUNDAY)], $tv, $terms), '2026-06-20', [], $summaryEngagement);
check('summary category shares', [null, [['percent' => 25.0, 'minutes' => 360]], 'sunday'], [$split['categoryPercent'], $split['categoryShares'], $split['waivedCategory']]);
check('api ping category shares', true, in_array('categoryShares', KimaiPlugin\DrehzettelBundle\Domain\ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));
