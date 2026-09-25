<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetViewBuilder;

/*
 * Day N behind the 6th/7th-day surcharge: the n-th working day of the ISO
 * calendar week (TV FFS TZ 5.4.3.1/5.4.3.4). No count across week boundaries.
 */

// One 8 h day per date: 'YYYY-MM-DD' or [date, override, type].
function weekDays(array $dates): array
{
    return array_map(static function (string|array $d): DayInput {
        [$date, $override, $type] = is_array($d) ? $d + [1 => null, 2 => DayType::WORKDAY] : [$d, null, DayType::WORKDAY];

        return new DayInput(at($date, '08:00'), at($date, '16:45'), type: $type, breakMinutes: 45, productionDay: $override);
    }, $dates);
}

function dayNumbers(array $inputs, ?KimaiPlugin\DrehzettelBundle\Domain\Ruleset $rules = null): array
{
    return array_map(static fn ($d): int => $d->dayNumber, weekCalc()->calc($inputs, $rules ?? Rulesets::quarterHour(), null)->days);
}

$tv = Rulesets::tvFfs2024();
$app = Rulesets::quarterHour();

// Wed 17 - Tue 23 June 2026: Mon/Tue start the next ISO week as day 1/2, no surcharge.
check('calendar week restarts', [[1, 2, 3, 4, 5], [1, 2]], [dayNumbers(weekDays(['2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21'])), dayNumbers(weekDays(['2026-06-22', '2026-06-23']))]);
check('calendar week restart no surcharge', null, weekCalc()->calc(weekDays(['2026-06-22', '2026-06-23']), $app, null)->days[0]->dayCountShare);

// Mon, Tue, (Wed off), Thu-Sun: a day off does not reset, Sunday is the 6th working day.
$offWednesday = weekDays(['2026-06-15', '2026-06-16', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21']);
check('calendar week counts over a gap', [1, 2, 3, 4, 5, 6], dayNumbers($offWednesday));
check('calendar week gap 6th day pooled (tv)', 480, weekCalc()->calc($offWednesday, $tv, null)->weeklyPoolMinutes);

// Seven days: day 6 and 7 get the fixed surcharges of the quarter-hour preset.
$seven = weekCalc()->calc(weekDays(['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21']), $app, null);
check('calendar week 6th/7th', [6, 7, 2500, 5000], [$seven->days[5]->dayNumber, $seven->days[6]->dayNumber, $seven->days[5]->dayCountShare?->basisPoints, $seven->days[6]->dayCountShare?->basisPoints]);

// Override: its own day only, the other days keep their position.
check('calendar week override', [1, 6, 3], dayNumbers(weekDays(['2026-06-15', ['2026-06-16', 6], '2026-06-17'])));
$overridden = weekCalc()->calc(weekDays(['2026-06-15', ['2026-06-16', 6]]), $app, null)->days;
check('override kept on result', [null, 6], [$overridden[0]->productionDay, $overridden[1]->productionDay]);

// Badge: from day 6, or when overridden; never on day 1-5 counted.
check('badge shown', [false, true, true], [$seven->days[4]->showsDayNumber(), $seven->days[5]->showsDayNumber(), $overridden[1]->showsDayNumber()]);

// DST: Europe/Berlin springs forward on Sun 29 March 2026; the week still counts 7 days.
check('calendar week dst', [1, 2, 3, 4, 5, 6, 7], dayNumbers(weekDays(['2026-03-23', '2026-03-24', '2026-03-25', '2026-03-26', '2026-03-27', '2026-03-28', '2026-03-29'])));

// PDF row label below the date.
$builder = new TimesheetViewBuilder(stubLabels());
$meta = new TimesheetMeta('X', 'Y', 'Z', 'de', true);
$period = Period::week(2026, 25, new DateTimeZone('Europe/Berlin'));
$view = $builder->build($meta, $period, [$seven], $app, new PdfOptions([]));
check('view: day number label from day 6', ['', 'drehzettel.production_day.label(%number%=6)'], [$view['weeks'][0]['rows'][4]['day_number'], $view['weeks'][0]['rows'][5]['day_number']]);

// Travel is paid like work time without surcharges and is no working time (TZ 12.1; PA FAQ S. 9):
// a travel day is no working day. Mon travel, Tue-Sun shooting makes Sunday day 6, not 7.
$travelMonday = weekDays([['2026-06-15', null, DayType::TRAVEL], '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21']);
$travelWeek = weekCalc()->calc($travelMonday, $app, null);
check('travel day not counted', [5, 6], [$travelWeek->days[5]->dayNumber, $travelWeek->days[6]->dayNumber]);
check('travel day no badge', false, $travelWeek->days[0]->showsDayNumber());

// A travel day after five working days is no 6th day: no weekly pool from travel time.
$travelSaturday = weekDays(['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', ['2026-06-20', null, DayType::TRAVEL]]);
check('travel day not pooled (tv)', 0, weekCalc()->calc($travelSaturday, $tv, null)->weeklyPoolMinutes);
