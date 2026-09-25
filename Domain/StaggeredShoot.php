<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;

/**
 * Staggered shoot (versetzter Dreh), TV FFS TZ 5.6.3 sentence 2:
 *
 *   "Sofern es sich um einen Sonntag oder die Feiertage Heilige Drei Könige,
 *   Fronleichnam, Mariä Himmelfahrt oder Allerheiligen innerhalb der Phase des
 *   1. bis 5. Produktionstages einer Kalenderwoche handelt (versetzter Dreh),
 *   wird kein Zuschlag gezahlt, unbeschadet bleibt der Anspruch auf einen
 *   bezahlten Ruhetag gem. 5.6.2."
 *
 *   Wed-Sun shooting: Sunday is day 5 -> no Sunday surcharge
 *   Mon-Sun shooting: Sunday is day 7 -> Sunday surcharge
 *   only Sunday:      Sunday is day 1 -> no Sunday surcharge (literal reading)
 *
 * Whether a date is a public holiday at all comes from the day's category
 * (holiday plugin or override); which holiday it is comes from the date.
 * Other holidays (Christmas, 1 May, ...) keep their surcharge, also on a Sunday.
 */
final class StaggeredShoot
{
    // Heilige Drei Könige, Mariä Himmelfahrt, Allerheiligen.
    private const FIXED_HOLIDAYS = ['01-06', '08-15', '11-01'];

    // Fronleichnam: the Thursday 60 days after Easter Sunday.
    private const CORPUS_CHRISTI_DAYS = 60;

    // True when the surcharge of this category is waived on that date and day N.
    public static function waives(DayCategory $category, \DateTimeImmutable $date, int $dayNumber): bool
    {
        if ($dayNumber > Units::WEEK_WORKDAYS) {
            return false;
        }

        return match ($category) {
            DayCategory::SUNDAY => true,
            DayCategory::HOLIDAY => self::isListedHoliday($date),
            default => false,
        };
    }

    // One of the four holidays TZ 5.6.3 names, by date alone.
    public static function isListedHoliday(\DateTimeImmutable $date): bool
    {
        if (in_array($date->format('m-d'), self::FIXED_HOLIDAYS, true)) {
            return true;
        }

        $year = (int) $date->format('Y');
        $corpusChristi = self::easterSunday($year)->modify(sprintf('+%d days', self::CORPUS_CHRISTI_DAYS));

        return $date->format('Y-m-d') === $corpusChristi->format('Y-m-d');
    }

    /**
     * Gregorian Easter Sunday (anonymous Gregorian algorithm, Meeus/Jones/Butcher):
     * 2025 -> 04-20, 2026 -> 04-05. Integer math only, no ext-calendar needed.
     */
    private static function easterSunday(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
