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
