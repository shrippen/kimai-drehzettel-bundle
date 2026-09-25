<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * AZV credit of one engagement up to a date (Azv): earned, not taken.
 *
 *   25 shooting days -> minutes 750, days 1, openMinutes 150
 */
final class AzvBalance
{
    /**
     * @param ?\DateTimeImmutable $countsFrom first counted day, null when not eligible
     * @param \DateTimeImmutable $until last counted day, inclusive
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly ?\DateTimeImmutable $countsFrom,
        public readonly \DateTimeImmutable $until,
        public readonly int $shootingDays,
    ) {
    }

    public static function none(\DateTimeImmutable $until): self
    {
        return new self(false, null, $until, 0);
    }

    public function minutes(): int
    {
        return Azv::minutes($this->shootingDays);
    }

    // Whole AZV days of 10 h.
    public function days(): int
    {
        return intdiv($this->minutes(), Azv::DAY_MINUTES);
    }

    // Credit beyond whole AZV days, paid through the time account (PA FAQ) unless the block completes.
    public function openMinutes(): int
    {
        return $this->minutes() % Azv::DAY_MINUTES;
    }
}
