<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Model\DayAddon;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Marks the shooting days on Kimai's own /contract
 * "Arbeitszeiten" page (requested 2026-09-23), using the same per-day
 * "addon" mechanism core itself uses for missing/unexpected-time markers
 * (App\WorkingTime\Model\Day::addAddon, rendered by core's
 * contract/status.html.twig as a "bg-{type}" cell + tooltip - no template of
 * ours involved). A shooting day is what the Drehzettel counts as one
 * (DayInputBuilder::shootingDates): an entry of the engagement, no travel day;
 * a saved FilmDay row is not needed (entries from the stopwatch have none).
 * Duration is always 0: this only marks the day, it never changes the
 * worked-time totals shown on that page.
 */
class ContractSubscriber implements EventSubscriberInterface
{
    private const ADDON_TYPE = 'drehzettel';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly DayInputBuilder $days,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkingTimeYearEvent::class => 'onWorkingTimeYear'];
    }

    public function onWorkingTimeYear(WorkingTimeYearEvent $event): void
    {
        $year = $event->getYear();
        $user = $year->getUser();

        $from = \DateTimeImmutable::createFromInterface($year->getYear())->modify('first day of january')->setTime(0, 0);
        $to = $from->modify('+1 year');
        $title = $this->translator->trans('drehzettel.contract.day');

        foreach ($this->engagements->findForUser($user) as $engagement) {
            foreach ($this->days->shootingDates($engagement, $from, $to) as $date) {
                $year->getDay(new \DateTimeImmutable($date, $from->getTimezone()))->addAddon(new DayAddon($title, 0, 0, self::ADDON_TYPE));
            }
        }
    }
}
