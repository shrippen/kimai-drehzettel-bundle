<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Page header of the form pages (ruleset editor, engagement rules, signature,
 * non-modal fallbacks): back link from the payload, optional delete confirmation.
 */
final class FormPageActions extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'drehzettel_form';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        if (isset($payload['delete'])) {
            $event->addDelete($payload['delete']);
        }
        if (isset($payload['back'])) {
            $event->addAction('back', ['url' => $payload['back'], 'title' => 'back']);
        }
    }
}
