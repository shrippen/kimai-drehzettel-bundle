<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;

/**
 * Day N for the 6th/7th-day surcharge, by the ruleset's StreakMode.
 *
 * CALENDAR_WEEK: n-th entry of the ISO week. An override (productionDay) sets N
 * for its own day only, the other days keep their position (unchanged behaviour).
 *
 * CONSECUTIVE ("Tag N in Folge"): counted across week boundaries.
 * A calendar day without an entry resets the count; travel days count.
 * An override sets N for its day, the following days continue from it:
 *
 *   date      Wed Thu Fri Sat Sun | Mon Tue | Wed  Thu Fri
 *   entry      x   x   x   x   x  |  x   x  |  -    x   x
 *   override                      |         |       4
 *   N          1   2   3   4   5  |  6   7  |       4   5
 *
 * Days are keyed by the entry's local date ("Y-m-d", DayInputBuilder's rule:
 * an entry from 20:00 to 06:00 belongs to its begin date only). Neighbours are
 * found on date keys, not by adding 24 h, so DST changes do not matter.
 */
final class Streak
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @param list<DayInput> $inputs sorted by begin, one per date
     * @param int $before N of the day right before the first input, 0 if it has no entry; CONSECUTIVE only
     * @return list<int> N per input
     */
    public static function numbers(array $inputs, int $before, StreakMode $mode): array
    {
        if ($mode === StreakMode::CALENDAR_WEEK) {
            return self::inWeek($inputs);
        }
        if ($inputs === []) {
            return [];
        }

        $previousKey = self::previousKey(self::key($inputs[0]->begin));
        $previous = $before;

        $numbers = [];
        foreach ($inputs as $input) {
            $key = self::key($input->begin);
            $counted = self::nextKey($previousKey) === $key ? $previous + 1 : 1;
            $previous = $input->productionDay ?? $counted;
            $previousKey = $key;
            $numbers[] = $previous;
        }

        return $numbers;
    }

    /**
     * @param list<DayInput> $inputs
     * @return list<int>
     */
    private static function inWeek(array $inputs): array
    {
        $numbers = [];
        foreach ($inputs as $index => $input) {
            $numbers[] = $input->productionDay ?? $index + 1;
        }

        return $numbers;
    }

    // Worth a badge: every day in a row; in the calendar week only surcharge days and overrides.
    public static function shown(DayResult $day, StreakMode $mode): bool
    {
        return $mode === StreakMode::CONSECUTIVE || $day->dayNumber > Units::WEEK_WORKDAYS || $day->productionDay !== null;
    }

    // N carried from the last day of one week into the first day of the next, 0 after a gap.
    public static function carry(DayResult $last, DayInput $next): int
    {
        return self::nextKey(self::key($last->begin)) === self::key($next->begin) ? $last->dayNumber : 0;
    }

    public static function key(\DateTimeImmutable $date): string
    {
        return $date->format(self::DATE_FORMAT);
    }

    // "2026-03-29" -> "2026-03-28". Calendar arithmetic in UTC: no DST gaps.
    public static function previousKey(string $key): string
    {
        return self::utc($key)->modify('-1 day')->format(self::DATE_FORMAT);
    }

    public static function nextKey(string $key): string
    {
        return self::utc($key)->modify('+1 day')->format(self::DATE_FORMAT);
    }

    private static function utc(string $key): \DateTimeImmutable
    {
        return new \DateTimeImmutable($key, new \DateTimeZone('UTC'));
    }
}
