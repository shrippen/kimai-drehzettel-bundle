<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\User;

// Default when no holiday plugin is installed: nothing is a holiday, days keep falling back to the weekday.
class NullHolidayLookup implements HolidayLookupInterface
{
    public function isHoliday(User $user, \DateTimeImmutable $date): bool
    {
        return false;
    }
}
