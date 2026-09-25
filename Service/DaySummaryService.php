<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\DaySummary;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * One day's figures for external clients. The whole ISO week is calculated,
 * as on the week page: weekly overtime and the 6th/7th day need it.
 */
class DaySummaryService
{
    public function __construct(
        private readonly FilmWeekService $weeks,
        private readonly ComplianceChecker $compliance,
        private readonly AzvService $azv,
    ) {
    }

    /**
     * @return array<string, mixed> see DaySummary::of()
     */
    public function summary(Engagement $engagement, \DateTimeImmutable $date): array
    {
        $isoYear = (int) $date->format('o');
        $isoWeek = (int) $date->format('W');
        $period = Period::week($isoYear, $isoWeek, $engagement->getUser()->getDateTimezone());

        $week = $this->weeks->week($engagement, $isoYear, $isoWeek);
        $warnings = $this->compliance->check($week, $this->weeks->lastDayBefore($engagement, $period->from));

        $azv = $this->azv->balance($engagement, $date->modify('+1 day'));

        return DaySummary::of($week, $date->format('Y-m-d'), $warnings, $engagement, $azv);
    }
}
