<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * "…" menu of one ruleset row. Payload: key (preset key or custom name) and,
 * for custom rulesets only, id.
 */
final class RulesetActions extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'drehzettel_ruleset';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $id = $payload['id'] ?? null;

        if ($id !== null) {
            $event->addEdit($this->path('drehzettel_ruleset_edit', ['id' => $id]), false);
        }
        $event->addAction('copy', ['url' => $this->path('drehzettel_ruleset_new', ['from' => $payload['key']]), 'title' => 'drehzettel.ruleset.copy']);
        if ($id !== null) {
            $event->addDelete($this->path('drehzettel_ruleset_delete', ['id' => $id]));
        }
    }
}
