<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber\Actions;

use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * Page header of the week: save, week PDF, month PDF, mail; managers get the
 * engagement submenu (edit, rules, delete).
 */
final class WeekActions extends AbstractActionsSubscriber
{
    private const FORM_ID = 'drehzettel-form';

    public static function getActionName(): string
    {
        return 'drehzettel_week';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $engagement = $payload['engagement'] ?? null;
        $view = $payload['view'] ?? null;
        if (!$engagement instanceof Engagement || !\is_array($view)) {
            return;
        }
        $week = ['id' => $engagement->getId(), 'year' => $view['year'], 'week' => $view['week']];
        $month = ['id' => $engagement->getId(), 'year' => $view['month_year'], 'month' => $view['month']];

        // Submits the week form from the page header (HTML form attribute).
        $event->addAction('save', [
            'buttonType' => 'submit',
            'class' => 'btn-primary',
            'attr' => ['form' => self::FORM_ID],
            'title' => 'action.save',
        ]);
        $event->addAction('pdf', ['url' => $this->path('drehzettel_week_pdf', $week), 'target' => '_blank', 'title' => 'drehzettel.action.week_pdf']);
        $event->addAction('calendar', ['url' => $this->path('drehzettel_month_pdf', $month), 'target' => '_blank', 'title' => 'drehzettel.action.month_pdf']);
        $event->addAction('mail', ['url' => $this->path('drehzettel_week_mail', $week), 'class' => 'modal-ajax-form', 'title' => 'drehzettel.action.mail']);

        if (!$this->isGranted('drehzettel_manage')) {
            return;
        }

        $id = ['id' => $engagement->getId()];
        $event->addActionToSubmenu('edit', 'edit', ['url' => $this->path('drehzettel_engagement_edit', $id), 'class' => 'modal-ajax-form', 'title' => 'action.edit']);
        $event->addActionToSubmenu('edit', 'rules', ['url' => $this->path('drehzettel_engagement_rules', $id), 'title' => 'drehzettel.rules.edit']);
        $event->addActionToSubmenu('edit', 'trash', ['url' => $this->path('drehzettel_engagement_delete', $id), 'class' => 'modal-ajax-form text-red', 'title' => 'action.delete']);
    }
}
