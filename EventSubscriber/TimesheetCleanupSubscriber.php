<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Entity\Timesheet;
use App\Event\TimesheetDeleteMultiplePreEvent;
use App\Event\TimesheetDeletePreEvent;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * FilmDay rows are keyed by (engagement, date) and shared by every timesheet entry that
 * lands on that date (see TimesheetFormExtension) - by design, not a bug. But that also
 * means deleting the last entry of a day must delete its FilmDay row too: otherwise a
 * brand-new entry created later on the same date silently inherits the deleted entry's
 * catering/category/note, which looks like data appearing out of nowhere rather than the
 * result of any rule (see FilmDayService::deleteIfOrphaned()). Both events fire before
 * the delete actually happens, so the timesheet(s) being removed are still in the
 * database when "how many entries are left on this day" is counted - they must be
 * excluded from that count by hand.
 */
class TimesheetCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EngagementService $engagements,
        private readonly FilmDayService $filmDays,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetDeletePreEvent::class => 'onDelete',
            TimesheetDeleteMultiplePreEvent::class => 'onDeleteMultiple',
        ];
    }

    public function onDelete(TimesheetDeletePreEvent $event): void
    {
        $this->cleanUp([$event->getTimesheet()]);
    }

    public function onDeleteMultiple(TimesheetDeleteMultiplePreEvent $event): void
    {
        $this->cleanUp($event->getTimesheets());
    }

    /**
     * @param list<Timesheet> $deleted
     */
    private function cleanUp(array $deleted): void
    {
        $deletedIds = array_map(static fn (Timesheet $timesheet) => $timesheet->getId(), $deleted);

        // Group by (engagement, date) first: a multi-delete can hit several entries of
        // the same day, and each day must only be checked once.
        $days = [];
        foreach ($deleted as $timesheet) {
            $engagement = $this->engagements->activeFor($timesheet);
            if ($engagement === null) {
                continue;
            }
            $date = EngagementService::dateOf($timesheet);
            $days[$engagement->getId() . '_' . $date->format('Y-m-d')] = [$engagement, $date];
        }

        foreach ($days as [$engagement, $date]) {
            $this->filmDays->deleteIfOrphaned($engagement, $date, $deletedIds);
        }
    }
}
