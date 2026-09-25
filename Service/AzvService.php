<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\Azv;
use KimaiPlugin\DrehzettelBundle\Domain\AzvBalance;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * AZV credit of an engagement (Domain\Azv): counts its shooting days
 * from the engagement start (not before 2025-05-01) up to a date.
 */
class AzvService
{
    public function __construct(private readonly DayInputBuilder $inputs)
    {
    }

    /**
     * @param \DateTimeImmutable $to end, exclusive: the Monday after the week, the day after a date
     * @param array<string, FilmDayDraft> $drafts unsaved film day data by date, for live previews
     */
    public function balance(Engagement $engagement, \DateTimeImmutable $to, array $drafts = []): AzvBalance
    {
        // By local date: an API date parsed in the server zone still means that calendar day.
        $to = new \DateTimeImmutable($to->format('Y-m-d'), $engagement->getUser()->getDateTimezone());
        $until = $to->modify('-1 day');
        if (!Azv::eligible($engagement)) {
            return AzvBalance::none($until);
        }

        $from = Azv::countsFrom($engagement);
        $days = $from < $to ? $this->inputs->shootingDays($engagement, $from, $to, $drafts) : 0;

        return new AzvBalance(true, $from, $until, $days);
    }

    // Up to today, or to the engagement's last day when it ended before.
    public function current(Engagement $engagement): AzvBalance
    {
        $zone = $engagement->getUser()->getDateTimezone();
        $tomorrow = new \DateTimeImmutable('tomorrow', $zone);
        $end = $engagement->getValidTo();
        if ($end === null) {
            return $this->balance($engagement, $tomorrow);
        }

        $afterEnd = (new \DateTimeImmutable($end->format('Y-m-d'), $zone))->modify('+1 day');

        return $this->balance($engagement, min($tomorrow, $afterEnd));
    }
}
