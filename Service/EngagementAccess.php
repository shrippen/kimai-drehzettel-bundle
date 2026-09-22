<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Who may see or change which engagement.
 *
 *   own engagement   permission "drehzettel"        weeks, PDF, signature
 *   any engagement   permission "drehzettel_manage" plus create, edit, rules, delete
 */
class EngagementAccess
{
    private const PERMISSION_OWN = 'drehzettel';
    private const PERMISSION_MANAGE = 'drehzettel_manage';

    public function __construct(private readonly Security $security)
    {
    }

    public function canManage(): bool
    {
        return $this->security->isGranted(self::PERMISSION_MANAGE);
    }

    public function canView(Engagement $engagement): bool
    {
        if ($this->canManage()) {
            return true;
        }

        return $this->security->isGranted(self::PERMISSION_OWN)
            && $engagement->getUser()?->getId() === $this->security->getUser()?->getId();
    }

    public function assertView(Engagement $engagement): void
    {
        if (!$this->canView($engagement)) {
            throw new AccessDeniedException('This engagement belongs to someone else.');
        }
    }

    public function assertManage(): void
    {
        if (!$this->canManage()) {
            throw new AccessDeniedException('Managing engagements needs the permission drehzettel_manage.');
        }
    }
}
