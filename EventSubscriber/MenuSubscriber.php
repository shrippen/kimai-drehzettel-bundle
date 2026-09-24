<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Drehzettel entry shows up for people who have an engagement, and for
 * managers. Everyone else keeps a plain Kimai menu.
 */
class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EngagementRepository $engagements,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMainMenu', -10],
        ];
    }

    public function onMainMenu(ConfigureMainMenuEvent $event): void
    {
        $user = $this->security->getUser();
        if ($user === null || !$this->security->isGranted('drehzettel')) {
            return;
        }

        $isManager = $this->security->isGranted('drehzettel_manage');
        if (!$isManager && $this->engagements->countForUser($user) === 0) {
            return;
        }

        $item = new MenuItemModel('drehzettel', 'drehzettel.menu', 'drehzettel_overview', [], 'fas fa-clapperboard');
        $item->setTranslationDomain('messages');
        $event->getMenu()->addChild($item);
    }
}
