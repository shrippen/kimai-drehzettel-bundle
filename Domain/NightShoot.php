<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * Night shoot day boundary, TV FFS TZ 5.2.4 sentence 2:
 *
 *   "Im Falle von Nachtdreharbeiten beginnt kein neuer Arbeitstag am
 *   2. Kalendertag, soweit an diesem die Arbeit um 4 Uhr beendet ist."
 *
 * A working day is keyed by its begin date, so one entry past midnight is one
 * working day already. byWorkingDay() adds a separate entry after midnight to
 * the night shoot of the day before when it ends by 04:00:
 *
 *   Sat 18:00-24:00 + Sun 00:00-03:00  -> one working day, Sat 18:00-Sun 03:00
 *   Sat 18:00-24:00 + Sun 00:00-05:00  -> two working days (ends after 04:00)
 *   Sat 08:00-17:00 + Sun 01:00-03:00  -> two working days (Saturday was no night shoot)
 *
 * Night shoot: the day before worked into the night, until 22:00 or later (TZ 5.5.1).
 * What happens past 04:00 the text leaves open (a new working day, but from
 * when?); minutesPastCutoff() lets callers point at such days.
 */
final class NightShoot
{
    public const CUTOFF_MINUTES = 4 * Units::MINUTES_PER_HOUR;

    private const NIGHT_FROM_MINUTES = 22 * Units::MINUTES_PER_HOUR;
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Entries to working days: earliest begin to latest end of the entries of one
     * begin date, plus the night shoot continuations of the next date.
     *
     * @param list<array{\DateTimeImmutable, \DateTimeImmutable}> $entries begin/end
     * @return array<string, array{\DateTimeImmutable, \DateTimeImmutable}> spans by date (Y-m-d), sorted
     */
    public static function byWorkingDay(array $entries): array
    {
        usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $spans = [];
        foreach ($entries as [$begin, $end]) {
            $key = $begin->format(self::DATE_FORMAT);
            $previousKey = $begin->modify('-1 day')->format(self::DATE_FORMAT);
            if (isset($spans[$previousKey]) && self::continues($spans[$previousKey][1], $begin, $end)) {
                $key = $previousKey;
            }

            $known = $spans[$key] ?? [$begin, $end];
            $spans[$key] = [min($known[0], $begin), max($known[1], $end)];
        }
        ksort($spans);

        return $spans;
    }

    // Minutes worked on the next calendar day beyond 04:00, 0 when the day ends by then: 18:00-05:30 -> 330.
    public static function minutesPastCutoff(\DateTimeImmutable $begin, \DateTimeImmutable $end): int
    {
        $midnight = $begin->setTime(0, 0)->modify('+1 day');
        $after = intdiv($end->getTimestamp() - $midnight->getTimestamp(), Units::SECONDS_PER_MINUTE);

        return $after > self::CUTOFF_MINUTES ? $after : 0;
    }

    // An entry after midnight that ends by 04:00, after a day that worked until 22:00 or later.
    private static function continues(\DateTimeImmutable $previousEnd, \DateTimeImmutable $begin, \DateTimeImmutable $end): bool
    {
        $midnight = $begin->setTime(0, 0);
        $cutoff = $midnight->modify(sprintf('+%d minutes', self::CUTOFF_MINUTES));
        $nightFrom = $midnight->modify(sprintf('-%d minutes', Units::MINUTES_PER_DAY - self::NIGHT_FROM_MINUTES));

        return $end <= $cutoff && $previousEnd >= $nightFrom;
    }
}
