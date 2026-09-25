<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

// Page header of the overview: new engagement (modal), rulesets, signature.
final class OverviewActions extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'drehzettel_overview';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if ($this->isGranted('drehzettel_manage')) {
            $event->addAction('create', [
                'url' => $this->path('drehzettel_engagement_new'),
                'class' => 'modal-ajax-form',
                'title' => 'drehzettel.engagement.new',
            ]);
            $event->addAction('list', ['url' => $this->path('drehzettel_ruleset_list'), 'title' => 'drehzettel.rulesets']);
        }

        $event->addAction('invoice-template', ['url' => $this->path('drehzettel_signature'), 'title' => 'drehzettel.signature']);
    }
}
