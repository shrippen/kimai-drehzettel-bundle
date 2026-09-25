<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

// Page header of the ruleset list: back to the overview.
final class RulesetListActions extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'drehzettel_rulesets';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $event->addAction('back', ['url' => $this->path('drehzettel_overview'), 'title' => 'back']);
    }
}
