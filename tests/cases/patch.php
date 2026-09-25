<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayCategory;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;

function travelDay(): FilmDay
{
    $day = new FilmDay();
    $day->setBreakMinutes(0);
    $day->setCatering(Catering::YES);
    $day->setCategory(DayCategory::HOLIDAY);
    $day->setDayType(DayType::TRAVEL);
    $day->setProductionDay(6);
    $day->setNote('Reise');

    return $day;
}

function dayFields(FilmDay $d): array
{
    return [$d->getBreakMinutes(), $d->getCatering(), $d->getCategory(), $d->getDayType(), $d->getProductionDay(), $d->getNote()];
}

function patchError(array $body): ?string
{
    try {
        FilmDayPatch::fromArray($body);

        return null;
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
}

// Only sent fields change: {"breakMinutes":30} keeps travel/6/catering/note.
$day = travelDay();
FilmDayPatch::fromArray(['breakMinutes' => 30])->applyTo($day);
check('patch keeps unsent fields', [30, Catering::YES, DayCategory::HOLIDAY, DayType::TRAVEL, 6, 'Reise'], dayFields($day));

// Every field, null resets to the ruleset default.
$day = travelDay();
FilmDayPatch::fromArray(['breakMinutes' => null, 'catering' => false, 'category' => null, 'dayType' => 'workday', 'productionDay' => null, 'note' => '  '])->applyTo($day);
check('patch all fields', [null, Catering::NO, null, DayType::WORKDAY, null, null], dayFields($day));

$day = new FilmDay();
FilmDayPatch::fromArray(['breakMinutes' => '45', 'catering' => true, 'category' => 'sunday', 'dayType' => 'travel', 'productionDay' => 7, 'note' => ' Nachtdreh '])->applyTo($day);
check('patch new day', [45, Catering::YES, DayCategory::SUNDAY, DayType::TRAVEL, 7, 'Nachtdreh'], dayFields($day));

// Bad input is rejected, not clamped or dropped silently.
check('patch empty body ok', null, patchError([]));
check('patch break negative', 'breakMinutes must be an integer from 0 to 720.', patchError(['breakMinutes' => -300]));
check('patch break too long', 'breakMinutes must be an integer from 0 to 720.', patchError(['breakMinutes' => 721]));
check('patch break fraction', 'breakMinutes must be an integer from 0 to 720.', patchError(['breakMinutes' => 30.5]));
check('patch break text', 'breakMinutes must be an integer from 0 to 720.', patchError(['breakMinutes' => 'abc']));
check('patch catering', 'catering must be a boolean.', patchError(['catering' => 'maybe']));
check('patch category', 'Unknown category "bogus".', patchError(['category' => 'bogus']));
check('patch day type', 'Unknown dayType "holiday".', patchError(['dayType' => 'holiday']));
check('patch day type null', 'Unknown dayType "".', patchError(['dayType' => null]));
check('patch production day', 'productionDay must be an integer from 1 to 7.', patchError(['productionDay' => 8]));
check('patch note long', 'note must be a string of at most 500 characters.', patchError(['note' => str_repeat('x', 501)]));
check('patch note type', 'note must be a string of at most 500 characters.', patchError(['note' => ['x']]));
check('patch note 500 ok', null, patchError(['note' => str_repeat('ä', 500)]));
