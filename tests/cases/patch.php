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

// Extra pay: integer cents 0..10,000,000, null resets to 0.
$day = travelDay();
FilmDayPatch::fromArray(['extraPayCents' => 5000])->applyTo($day);
check('patch extra pay', [5000, 0, 'Reise'], [$day->getExtraPayCents(), $day->getBreakMinutes(), $day->getNote()]);
FilmDayPatch::fromArray(['note' => 'x'])->applyTo($day);
check('patch keeps extra pay', 5000, $day->getExtraPayCents());
FilmDayPatch::fromArray(['extraPayCents' => null])->applyTo($day);
check('patch extra pay null resets', 0, $day->getExtraPayCents());
check('patch extra pay max ok', null, patchError(['extraPayCents' => 10000000]));
check('patch extra pay too high', 'extraPayCents must be an integer from 0 to 10000000.', patchError(['extraPayCents' => 10000001]));
check('patch extra pay negative', 'extraPayCents must be an integer from 0 to 10000000.', patchError(['extraPayCents' => -1]));
check('patch extra pay fraction', 'extraPayCents must be an integer from 0 to 10000000.', patchError(['extraPayCents' => 12.5]));
check('patch extra pay bool', 'extraPayCents must be an integer from 0 to 10000000.', patchError(['extraPayCents' => true]));

// Shooting day of the production: integer 1..999 or null, informational only.
$day = travelDay();
FilmDayPatch::fromArray(['shootingDayNumber' => 37])->applyTo($day);
check('patch shooting day', [37, 6, 'Reise'], [$day->getShootingDayNumber(), $day->getProductionDay(), $day->getNote()]);
FilmDayPatch::fromArray(['note' => 'x'])->applyTo($day);
check('patch keeps shooting day', 37, $day->getShootingDayNumber());
FilmDayPatch::fromArray(['shootingDayNumber' => '38'])->applyTo($day);
check('patch shooting day digit string', 38, $day->getShootingDayNumber());
FilmDayPatch::fromArray(['shootingDayNumber' => null])->applyTo($day);
check('patch shooting day null clears', null, $day->getShootingDayNumber());
check('patch shooting day bounds ok', [null, null], [patchError(['shootingDayNumber' => 1]), patchError(['shootingDayNumber' => 999])]);
foreach (['zero' => 0, 'too high' => 1000, 'negative' => -1, 'fraction' => 1.5, 'bool' => true, 'text' => 'abc'] as $name => $bad) {
    check('patch shooting day ' . $name, 'shootingDayNumber must be an integer from 1 to 999.', patchError(['shootingDayNumber' => $bad]));
}
