<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\Rounding;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;

$cases = [
    [RoundingUnit::MINUTE, RoundingMode::UP, 707, 707],
    [RoundingUnit::QUARTER, RoundingMode::UP, 707, 720],
    [RoundingUnit::QUARTER, RoundingMode::DOWN, 707, 705],
    [RoundingUnit::QUARTER, RoundingMode::NEAREST, 707, 705],
    [RoundingUnit::QUARTER, RoundingMode::NEAREST, 713, 720],
    [RoundingUnit::QUARTER, RoundingMode::NEAREST, 720, 720],
    [RoundingUnit::HOUR, RoundingMode::UP, 61, 120],
    [RoundingUnit::HOUR, RoundingMode::DOWN, 119, 60],
    [RoundingUnit::HALF_HOUR, RoundingMode::NEAREST, 45, 60],
];
foreach ($cases as [$unit, $mode, $in, $out]) {
    check("rounding {$unit->name} {$mode->name} $in", $out, (new Rounding($unit, $mode))->apply($in));
}

// Clock times: begin rounds opposite to the work-time mode, end the same way.
$zone = new DateTimeZone('Europe/Berlin');
$clockCases = [
    [RoundingMode::NEAREST, '2026-09-24 11:50', true, '2026-09-24 11:45'],
    [RoundingMode::NEAREST, '2026-09-24 22:09', false, '2026-09-24 22:15'],
    [RoundingMode::UP, '2026-09-24 07:52', true, '2026-09-24 07:45'],
    [RoundingMode::UP, '2026-09-24 22:01', false, '2026-09-24 22:15'],
    [RoundingMode::DOWN, '2026-09-24 07:52', true, '2026-09-24 08:00'],
    [RoundingMode::DOWN, '2026-09-24 22:14', false, '2026-09-24 22:00'],
    [RoundingMode::NEAREST, '2026-09-24 23:53', false, '2026-09-25 00:00'],
    [RoundingMode::NEAREST, '2026-09-24 11:50:40', true, '2026-09-24 11:45'],
];
foreach ($clockCases as [$mode, $in, $isBegin, $out]) {
    $rounded = (new Rounding(RoundingUnit::QUARTER, $mode))->clock(new DateTimeImmutable($in, $zone), $isBegin);
    check("clock {$mode->name} $in " . ($isBegin ? 'begin' : 'end'), $out, $rounded->format('Y-m-d H:i'));
}

// Worked example from a real week: 11:50-22:09, 45 min break -> 11:45-22:15, 9:45 h work.
$day = dayCalc()->calc(
    new KimaiPlugin\DrehzettelBundle\Domain\DayInput(at('2026-09-24', '11:50'), at('2026-09-24', '22:09'), breakMinutes: 45),
    KimaiPlugin\DrehzettelBundle\Domain\Rulesets::quarterHour(),
    null,
);
check('rounded day begin', '11:45', $day->begin->format('H:i'));
check('rounded day end', '22:15', $day->end->format('H:i'));
check('rounded day work', 585, $day->workMinutes);
