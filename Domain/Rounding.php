<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\RoundingMode;
use KimaiPlugin\DrehzettelBundle\Enum\RoundingUnit;

final class Rounding
{
    public function __construct(
        public readonly RoundingUnit $unit,
        public readonly RoundingMode $mode,
    ) {
    }

    // 11:47 with QUARTER: UP -> 12:00, DOWN -> 11:45, NEAREST -> 11:45.
    public function apply(int $minutes): int
    {
        $step = $this->unit->value;
        $rest = $minutes % $step;
        if ($rest === 0) {
            return $minutes;
        }

        $down = $minutes - $rest;

        return match ($this->mode) {
            RoundingMode::UP => $down + $step,
            RoundingMode::DOWN => $down,
            RoundingMode::NEAREST => $rest * 2 >= $step ? $down + $step : $down,
        };
    }
}
