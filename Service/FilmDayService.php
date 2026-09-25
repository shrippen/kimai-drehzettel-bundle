<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraftReader;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;
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
        private readonly EngagementService $engagements,
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
        int $extraPayCents = 0,
        ?int $shootingDayNumber = null,
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
        $day->setExtraPayCents($extraPayCents);
        $day->setShootingDayNumber($shootingDayNumber);

        $this->days->save($day);

        return $day;
    }

    /**
     * Week view fields of one day over the stored day: fields the form did not send keep their value.
     *
     * @param array<string, mixed> $fields see FilmDayDraftReader::read()
     */
    public function draft(Engagement $engagement, \DateTimeImmutable $date, array $fields): FilmDayDraft
    {
        $mode = $this->engagements->ruleset($engagement)->streakMode;

        return FilmDayDraftReader::read($fields, $this->days->findOne($engagement, $date), $mode);
    }

    // Changes only the patched fields; a new day starts from the entity defaults.
    public function patch(Engagement $engagement, \DateTimeImmutable $date, FilmDayPatch $patch): FilmDay
    {
        $day = $this->days->findOne($engagement, $date) ?? new FilmDay();
        $day->setEngagement($engagement);
        $day->setDate($date);
        $patch->applyTo($day);

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

        // A day wider, then matched by local date: see DayInputBuilder::spans().
        $entriesAround = $this->timesheets->findClosed($engagement->getUser(), $engagement->getProject(), $date->modify('-1 day'), $date->modify('+2 days'));
        foreach ($entriesAround as $entry) {
            if (EngagementService::dateOf($entry)->format('Y-m-d') === $date->format('Y-m-d') && !\in_array($entry->getId(), $excludedTimesheetIds, true)) {
                return;
            }
        }

        $this->days->delete($day);
    }
}
