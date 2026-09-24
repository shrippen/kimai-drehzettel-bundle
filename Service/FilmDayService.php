<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;

class FilmDayService
{
    public function __construct(
        private readonly FilmDayRepository $days,
        private readonly TimesheetRangeRepository $timesheets,
    ) {
    }

    // Creates the film day or updates the existing one for this date.
    public function save(
        Engagement $engagement,
        \DateTimeImmutable $date,
        ?int $breakMinutes,
        Catering $catering,
        ?DayCategory $category = null,
        DayType $type = DayType::WORKDAY,
        ?int $productionDay = null,
        ?string $note = null,
    ): FilmDay {
        $day = $this->days->findOne($engagement, $date) ?? new FilmDay();
        $day->setEngagement($engagement);
        $day->setDate($date);
        $day->setBreakMinutes($breakMinutes);
        $day->setCatering($catering);
        $day->setCategory($category);
        $day->setDayType($type);
        $day->setProductionDay($productionDay);
        $day->setNote($note);

        $this->days->save($day);

        return $day;
    }

    // A FilmDay row is only meaningful while some closed timesheet entry of this
    // engagement's user+project still lands on its date (see TimesheetFormExtension) -
    // otherwise it is orphaned: a leftover a user would never see a reason for, and
    // which silently pre-fills the next entry created on that date. Called both when a
    // timesheet is deleted and when editing one moves it off its original date, in each
    // case with that/those timesheet(s) - already excluded from what "still lands on
    // this date" means, since a moved entry's begin no longer falls in the old range and
    // a not-yet-deleted one must be named explicitly.
    public function deleteIfOrphaned(Engagement $engagement, \DateTimeImmutable $date, array $excludedTimesheetIds = []): void
    {
        $day = $this->days->findOne($engagement, $date);
        if ($day === null) {
            return;
        }

        $entriesOfDay = $this->timesheets->findClosed($engagement->getUser(), $engagement->getProject(), $date, $date->modify('+1 day'));
        foreach ($entriesOfDay as $entry) {
            if (!\in_array($entry->getId(), $excludedTimesheetIds, true)) {
                return;
            }
        }

        $this->days->delete($day);
    }
}
