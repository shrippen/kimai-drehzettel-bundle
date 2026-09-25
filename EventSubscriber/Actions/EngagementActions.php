<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

// "…" menu of one engagement row on the overview.
final class EngagementActions extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'drehzettel_engagement';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $engagement = $event->getPayload()['engagement'] ?? null;
        if (!$engagement instanceof Engagement) {
            return;
        }
        $id = ['id' => $engagement->getId()];

        $event->addAction('calendar', ['url' => $this->path('drehzettel_week', $id), 'title' => 'drehzettel.open']);
        if (!$this->isGranted('drehzettel_manage')) {
            return;
        }

        $event->addEdit($this->path('drehzettel_engagement_edit', $id));
        $event->addAction('settings', ['url' => $this->path('drehzettel_engagement_rules', $id), 'title' => 'drehzettel.rules.edit']);
        $event->addDelete($this->path('drehzettel_engagement_delete', $id));
    }
}
