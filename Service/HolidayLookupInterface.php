<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\User;

/**
 * Whether a date is a public holiday for a user. Implemented by
 * NullHolidayLookup when no holiday plugin is installed, or by
 * Holiday\HolidayBundleLookup when KimaiPlugin\HolidayBundle is present.
 */
interface HolidayLookupInterface
{
    public function isHoliday(User $user, \DateTimeImmutable $date): bool;
}
