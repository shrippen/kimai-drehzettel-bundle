<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\PeriodKind;

/**
 * Inclusive date range of a timesheet: one week, one month or any range.
 * Dates are calendar dates in the user's timezone.
 */
final class Period
{
    private function __construct(
        public readonly PeriodKind $kind,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
    ) {
        if ($to < $from) {
            throw new \InvalidArgumentException('Period ends before it starts.');
        }
    }

    public static function week(int $isoYear, int $isoWeek, \DateTimeZone $zone): self
    {
        $monday = (new \DateTimeImmutable('today', $zone))->setISODate($isoYear, $isoWeek)->setTime(0, 0);

        return new self(PeriodKind::WEEK, $monday, $monday->modify('+6 days'));
    }

    public static function month(int $year, int $month, \DateTimeZone $zone): self
    {
        $first = (new \DateTimeImmutable('today', $zone))->setDate($year, $month, 1)->setTime(0, 0);

        return new self(PeriodKind::MONTH, $first, $first->modify('last day of this month'));
    }

    public static function range(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    {
        return new self(PeriodKind::RANGE, $from->setTime(0, 0), $to->setTime(0, 0));
    }

    // Exclusive end, for [from, to) queries.
    public function endExclusive(): \DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->from->format('Y-m-d') && $day <= $this->to->format('Y-m-d');
    }

    // Y-m-d key -> midnight of that date in the period's zone, or null when invalid or outside.
    // '!' zeroes the time: without it, a Sunday parsed at 14:00 lies after "to" (Sunday 00:00).
    public function day(string $key): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $key, $this->from->getTimezone());
        if ($date === false || $date->format('Y-m-d') !== $key) {
            return null;
        }

        return $this->contains($date) ? $date : null;
    }
}
