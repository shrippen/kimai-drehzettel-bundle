<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Entity\Timesheet;
use App\Event\AbstractTimesheetEvent;
use App\Event\TimesheetCreatePostEvent;
use App\Event\TimesheetUpdatePostEvent;
use App\Event\TimesheetUpdatePreEvent;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Service\PendingFilmDays;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Runs after Kimai saved a timesheet entry (form, API, duplicate):
 *
 * - writes the film day fields queued by TimesheetFormExtension, to the engagement of
 *   the entry as saved - an admin may have changed user, project or date in the form;
 * - removes the FilmDay row an edit left behind when it moved the entry to another
 *   engagement or date (e.g. an API PATCH of begin), see FilmDayService::deleteIfOrphaned().
 *
 * The pre-update event still sees the entry's stored user/project/begin via Doctrine.
 */
class TimesheetSaveSubscriber implements EventSubscriberInterface
{
    /** @var \WeakMap<Timesheet, array{Engagement, \DateTimeImmutable}> */
    private \WeakMap $before;

    public function __construct(
        private readonly EngagementService $engagements,
        private readonly FilmDayService $filmDays,
        private readonly PendingFilmDays $pending,
        private readonly TimesheetRangeRepository $timesheets,
    ) {
        $this->before = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetCreatePostEvent::class => 'onSaved',
            TimesheetUpdatePreEvent::class => 'onUpdating',
            TimesheetUpdatePostEvent::class => 'onSaved',
        ];
    }

    public function onUpdating(TimesheetUpdatePreEvent $event): void
    {
        $timesheet = $event->getTimesheet();
        $stored = $this->timesheets->stored($timesheet);
        if ($stored === null) {
            return;
        }

        [$user, $project, $begin] = $stored;
        $date = EngagementService::dayOf($user, $begin);
        $engagement = $this->engagements->active($user, $project, $date);
        if ($engagement !== null) {
            $this->before[$timesheet] = [$engagement, $date];
        }
    }

    public function onSaved(AbstractTimesheetEvent $event): void
    {
        $timesheet = $event->getTimesheet();
        $patch = $this->pending->take($timesheet);
        $before = $this->before[$timesheet] ?? null;
        unset($this->before[$timesheet]);

        $engagement = $this->engagements->activeFor($timesheet);
        $date = $engagement !== null ? EngagementService::dateOf($timesheet) : null;
        if ($engagement !== null && $patch !== null) {
            $this->filmDays->patch($engagement, $date, $patch);
        }

        if ($before === null) {
            return;
        }

        // Still on the same engagement and date: nothing left behind.
        [$oldEngagement, $oldDate] = $before;
        if ($oldEngagement->getId() === $engagement?->getId() && $oldDate == $date) {
            return;
        }
        $this->filmDays->deleteIfOrphaned($oldEngagement, $oldDate);
    }
}
