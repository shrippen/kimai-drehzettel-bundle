<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;

/**
 * When the timesheet mail goes out automatically, and which period it holds:
 *
 *   weekly, Monday 07:00   -> last week      (the week of the day before)
 *   weekly, Friday 18:00   -> this week
 *   monthly, 1st at 07:00  -> last month
 *
 * Times are in the zone of the "now" passed in (the user's zone).
 */
final class MailSchedule
{
    public const DEFAULT_WEEKDAY = 1;
    public const DEFAULT_HOUR = 7;
    public const MAX_HOUR = 23;
    public const DAYS_PER_WEEK = 7;

    public function __construct(
        public readonly MailRhythm $rhythm,
        public readonly int $weekday = self::DEFAULT_WEEKDAY,
        public readonly int $hour = self::DEFAULT_HOUR,
    ) {
        if ($weekday < 1 || $weekday > self::DAYS_PER_WEEK || $hour < 0 || $hour > self::MAX_HOUR) {
            throw new \InvalidArgumentException('Weekday 1-7, hour 0-23.');
        }
    }

    // Latest send time at or before now; null when off.
    public function lastSlot(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        return match ($this->rhythm) {
            MailRhythm::OFF => null,
            MailRhythm::WEEKLY => $this->weeklySlot($now),
            MailRhythm::MONTHLY => $this->monthlySlot($now),
        };
    }

    // First send time after now; null when off.
    public function nextSlot(\DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $last = $this->lastSlot($now);
        if ($last === null) {
            return null;
        }
        if ($this->rhythm === MailRhythm::MONTHLY) {
            return $last->modify('first day of next month')->setTime($this->hour, 0);
        }

        return $last->modify('+7 days')->setTime($this->hour, 0);
    }

    // The week or month of the day before the slot.
    public function period(\DateTimeImmutable $slot): Period
    {
        $day = $slot->modify('-1 day');
        if ($this->rhythm === MailRhythm::MONTHLY) {
            return Period::month((int) $day->format('Y'), (int) $day->format('n'), $day->getTimezone());
        }

        return Period::week((int) $day->format('o'), (int) $day->format('W'), $day->getTimezone());
    }

    private function weeklySlot(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $slot = $now->setISODate((int) $now->format('o'), (int) $now->format('W'), $this->weekday)->setTime($this->hour, 0);

        return $slot > $now ? $slot->modify('-7 days') : $slot;
    }

    private function monthlySlot(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $slot = $now->modify('first day of this month')->setTime($this->hour, 0);

        return $slot > $now ? $slot->modify('first day of previous month')->setTime($this->hour, 0) : $slot;
    }
}
