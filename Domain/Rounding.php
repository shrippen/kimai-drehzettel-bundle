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

    /**
     * Rounds a clock time on its own wall clock. The mode is meant for the work time, so a
     * begin rounds the opposite way: UP (in the worker's favour) moves the begin earlier and
     * the end later, DOWN the other way round, NEAREST both to the nearest step.
     * 07:52 begin with QUARTER UP -> 07:45; 22:09 end -> 22:15. Seconds are dropped first.
     */
    public function clock(\DateTimeImmutable $time, bool $isBegin): \DateTimeImmutable
    {
        $minuteOfDay = (int) $time->format('G') * 60 + (int) $time->format('i');
        $mode = $isBegin ? match ($this->mode) {
            RoundingMode::UP => RoundingMode::DOWN,
            RoundingMode::DOWN => RoundingMode::UP,
            RoundingMode::NEAREST => RoundingMode::NEAREST,
        } : $this->mode;
        $rounded = (new self($this->unit, $mode))->apply($minuteOfDay);

        return $time->setTime(0, 0)->modify(sprintf('+%d minutes', $rounded));
    }
}
