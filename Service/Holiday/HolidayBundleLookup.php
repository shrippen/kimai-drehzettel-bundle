<?php

namespace KimaiPlugin\DrehzettelBundle\Service\Holiday;

use App\Entity\User;
use KimaiPlugin\DrehzettelBundle\Service\HolidayLookupInterface;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;

/**
 * Reads public holidays from the optional KimaiPlugin\HolidayBundle plugin
 * (github.com/shrippen/kimai-holiday-bundle), through the user's assigned
 * public holiday group. Only registered by DrehzettelExtension when that
 * plugin's classes are present (see excludes in services.yaml) - this class
 * must never be autowired unconditionally, or the container fails to
 * compile when the plugin is not installed.
 */
class HolidayBundleLookup implements HolidayLookupInterface
{
    public function __construct(
        private readonly UserWorkContract $contract,
        private readonly PublicHolidayRepository $holidays,
    ) {
    }

    public function isHoliday(User $user, \DateTimeImmutable $date): bool
    {
        $group = $this->contract->getPublicHolidayGroup($user);
        if ($group === null) {
            return false;
        }

        return $this->holidays->findOneByGroupAndDate($group, $date) !== null;
    }
}
