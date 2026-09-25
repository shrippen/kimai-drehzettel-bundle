<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\Streak;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * How many days in a row were worked right before a date, reaching back
 * across weeks (see Streak). Walks back one window at a time, one query each:
 *
 *   ... [window 2][window 1] date
 *            <-- stops at the first day without entry, at an override
 *                (N = override + days after it) or at the engagement start
 */
class ConsecutiveDayCounter
{
    private const WINDOW_DAYS = 14;

    public function __construct(private readonly DayInputBuilder $inputs)
    {
    }

    // N of the day before $date, 0 if that day has no entry in the engagement.
    public function before(Engagement $engagement, \DateTimeImmutable $date): int
    {
        $start = Streak::key($engagement->getValidFrom());
        $to = new \DateTimeImmutable(Streak::key($date), $engagement->getUser()->getDateTimezone());
        $cursor = Streak::previousKey(Streak::key($date));
        $count = 0;

        while ($cursor >= $start) {
            $from = $to->modify(sprintf('-%d days', self::WINDOW_DAYS));
            $days = $this->inputs->workedDays($engagement, $from, $to);

            // Walk back through this window; leave the loop only to fetch the next one.
            for ($fromKey = Streak::key($from); $cursor >= $fromKey; $cursor = Streak::previousKey($cursor)) {
                if (!array_key_exists($cursor, $days)) {
                    return $count;
                }
                if ($days[$cursor] !== null) {
                    return $days[$cursor] + $count;
                }
                ++$count;
            }

            $to = $from;
        }

        return $count;
    }
}
