<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

// Film day data as typed in the week view, before or without saving.
final class FilmDayDraft
{
    public function __construct(
        public readonly ?int $breakMinutes,
        public readonly Catering $catering,
        public readonly ?DayCategory $category,
        public readonly DayType $type,
        public readonly ?int $productionDay,
        public readonly ?string $note,
        public readonly int $extraPayCents = 0,
    ) {
    }
}
