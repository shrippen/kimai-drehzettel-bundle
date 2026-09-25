<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\Azv;
use KimaiPlugin\DrehzettelBundle\Domain\AzvBalance;
use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayDraft;
use KimaiPlugin\DrehzettelBundle\Domain\PdfOptions;
use KimaiPlugin\DrehzettelBundle\Domain\Period;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\TimesheetMeta;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Form\EngagementData;
use KimaiPlugin\DrehzettelBundle\Service\AzvService;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use KimaiPlugin\DrehzettelBundle\Service\TimesheetViewBuilder;

/*
 * AZV credit, TV FFS TZ 6.1-6.7: 2.5 h after 5 shooting days, 0.5 h per further one,
 * per block of 20 shooting days (10 h = one AZV day).
 */

// Kimai's User is stubbed: tests run without Kimai.
if (!class_exists('App\Entity\User')) {
    eval('namespace App\Entity; class User {
        public function getTimezone(): string { return "Europe/Berlin"; }
        public function getDateTimezone(): \DateTimeZone { return new \DateTimeZone("Europe/Berlin"); } }');
}

function azvEngagement(string $validFrom, string $ruleset = 'TV FFS 2024', ?bool $flag = null, ?string $validTo = null): Engagement
{
    $engagement = new Engagement();
    $engagement->setUser(new App\Entity\User());
    $engagement->setRulesetName($ruleset);
    $engagement->setValidFrom(new DateTimeImmutable($validFrom));
    $engagement->setValidTo($validTo === null ? null : new DateTimeImmutable($validTo));
    $engagement->setAzv($flag);

    return $engagement;
}

// Shooting days from fixed inputs, filtered like DayInputBuilder::shootingDays() (local date, validity, no travel).
function azvInputs(array $inputs): DayInputBuilder
{
    return new class($inputs) extends DayInputBuilder {
        public function __construct(private readonly array $all)
        {
        }

        public function shootingDays(Engagement $engagement, DateTimeImmutable $from, DateTimeImmutable $to, array $drafts = []): int
        {
            $count = 0;
            foreach ($this->all as $input) {
                $key = $input->begin->format('Y-m-d');
                $inside = $key >= $from->format('Y-m-d') && $key < $to->format('Y-m-d') && $key >= $engagement->getValidFrom()->format('Y-m-d')
                    && ($engagement->getValidTo() === null || $key <= $engagement->getValidTo()->format('Y-m-d'));
                $type = isset($drafts[$key]) ? $drafts[$key]->type : $input->type;
                $count += $inside && $type === DayType::WORKDAY ? 1 : 0;
            }

            return $count;
        }
    };
}

// n shooting days Mon-Fri from $monday, weekends off.
function weekdayShoots(string $monday, int $count): array
{
    $inputs = [];
    for ($date = new DateTimeImmutable($monday); count($inputs) < $count; $date = $date->modify('+1 day')) {
        if ((int) $date->format('N') <= 5) {
            $inputs[] = new DayInput($date->setTime(8, 0), $date->setTime(18, 45), breakMinutes: 45);
        }
    }

    return $inputs;
}

// Credit per number of shooting days: threshold at 5, block boundary at 20. TZ 6.4: the 2.5 h for
// days 21-25 come "ab dem 26. Drehtag" (PA FAQ: already with day 25; both 26 -> 13 h).
$credit = [];
foreach ([0, 4, 5, 6, 19, 20, 21, 24, 25, 26, 27, 40, 45, 46, 60, 65, 66] as $n) {
    $credit[$n] = Azv::minutes($n);
}
check('azv credit', [0 => 0, 4 => 0, 5 => 150, 6 => 180, 19 => 570, 20 => 600, 21 => 600, 24 => 600, 25 => 600, 26 => 780, 27 => 810, 40 => 1200, 45 => 1200, 46 => 1380, 60 => 1800, 65 => 1800, 66 => 1980], $credit);

// Balance: whole AZV days of 10 h and the rest.
$b26 = new AzvBalance(true, new DateTimeImmutable('2026-01-05'), new DateTimeImmutable('2026-02-09'), 26);
check('azv balance 26 days', [780, 1, 180], [$b26->minutes(), $b26->days(), $b26->openMinutes()]);
check('azv balance none', [false, 0, 0], [AzvBalance::none(new DateTimeImmutable())->eligible, AzvBalance::none(new DateTimeImmutable())->minutes(), AzvBalance::none(new DateTimeImmutable())->days()]);

// Default: TV FFS 2024 starting on or after 2025-05-01 (TZ 6.7); an explicit choice wins.
check('azv default', [true, false, false, true, false], [
    Azv::eligible(azvEngagement('2025-05-01')),
    Azv::eligible(azvEngagement('2025-04-30')),
    Azv::eligible(azvEngagement('2026-01-05', Rulesets::quarterHour()->name)),
    Azv::eligible(azvEngagement('2026-01-05', Rulesets::quarterHour()->name, true)),
    Azv::eligible(azvEngagement('2026-01-05', 'TV FFS 2024', false)),
]);

// 25 shooting days Mon-Fri from Mon 5 Jan 2026: day 5 = 2.5 h, day 20 = 10 h (one AZV day), day 25 still 10 h (TZ 6.4).
$shoots = weekdayShoots('2026-01-05', 25);
$service = new AzvService(azvInputs($shoots));
$tv = azvEngagement('2026-01-05');
$after = static fn (int $n): DateTimeImmutable => $shoots[$n - 1]->begin->setTime(0, 0)->modify('+1 day');
$series = [];
foreach ([4, 5, 6, 20, 21, 24, 25] as $n) {
    $series[$n] = $service->balance($tv, $after($n))->minutes();
}
check('azv 25 day streak', [4 => 0, 5 => 150, 6 => 180, 20 => 600, 21 => 600, 24 => 600, 25 => 600], $series);
$full = $service->balance($tv, $after(25));
check('azv streak balance', [true, '2026-01-05', '2026-02-06', 25, 1, 0], [$full->eligible, $full->countsFrom->format('Y-m-d'), $full->until->format('Y-m-d'), $full->shootingDays, $full->days(), $full->openMinutes()]);

// Weekends between do not break the row; travel days do not count.
$withTravel = [...weekdayShoots('2026-01-05', 4), new DayInput(at('2026-01-09', '08:00'), at('2026-01-09', '12:00'), type: DayType::TRAVEL), ...weekdayShoots('2026-01-12', 1)];
check('azv travel not counted', [5, 150], [(new AzvService(azvInputs($withTravel)))->balance($tv, new DateTimeImmutable('2026-01-13'))->shootingDays, (new AzvService(azvInputs($withTravel)))->balance($tv, new DateTimeImmutable('2026-01-13'))->minutes()]);

// A draft (live preview) turning a day into travel changes the count.
$draft = new FilmDayDraft(null, Catering::NO, null, DayType::TRAVEL, null, null);
check('azv draft travel', 24, $service->balance($tv, $after(25), ['2026-02-06' => $draft])->shootingDays);

// Started before 2025-05-01 and enabled by hand (TZ 6.7 transition): days before 1 May do not count.
$april = [...weekdayShoots('2025-04-28', 5)];
$running = azvEngagement('2025-04-28', 'TV FFS 2024', true);
$runningBalance = (new AzvService(azvInputs($april)))->balance($running, new DateTimeImmutable('2025-05-05'));
check('azv counts from 2025-05-01', ['2025-05-01', 2, 0], [$runningBalance->countsFrom->format('Y-m-d'), $runningBalance->shootingDays, $runningBalance->minutes()]);
check('azv before 2025-05-01 by default off', false, (new AzvService(azvInputs($april)))->balance(azvEngagement('2025-04-28'), new DateTimeImmutable('2025-05-05'))->eligible);

// Not eligible: no count at all.
$quarter = (new AzvService(azvInputs($shoots)))->balance(azvEngagement('2026-01-05', Rulesets::quarterHour()->name), $after(25));
check('azv not eligible', [false, null, 0, 0], [$quarter->eligible, $quarter->countsFrom, $quarter->shootingDays, $quarter->minutes()]);

// The row ends with the engagement: days after validTo do not count.
check('azv ends with engagement', 20, (new AzvService(azvInputs($shoots)))->balance(azvEngagement('2026-01-05', 'TV FFS 2024', null, '2026-01-30'), $after(25))->shootingDays);

// API shapes: dedicated endpoint and day summary key.
$tv->setProject(new App\Entity\Project());
$json = KimaiPlugin\DrehzettelBundle\Domain\ApiJson::azv($tv, $full);
check('api azv json', ['engagementId' => null, 'eligible' => true, 'countsFrom' => '2026-01-05', 'date' => '2026-02-06', 'shootingDays' => 25, 'minutes' => 600, 'days' => 1, 'openMinutes' => 0, 'dayMinutes' => 600, 'blockDays' => 20], $json);
check('api azv json not eligible', [false, null, 0], [KimaiPlugin\DrehzettelBundle\Domain\ApiJson::azv($tv, $quarter)['eligible'], KimaiPlugin\DrehzettelBundle\Domain\ApiJson::azv($tv, $quarter)['countsFrom'], KimaiPlugin\DrehzettelBundle\Domain\ApiJson::azv($tv, $quarter)['minutes']]);
$summaryWeek = weekCalc()->calc([$shoots[24]], Rulesets::tvFfs2024(), null);
check('summary azv minutes', [600, null, null], [
    KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-02-06', [], $tv, $full)['azvMinutesToDate'],
    KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-02-06', [], $tv, $quarter)['azvMinutesToDate'],
    KimaiPlugin\DrehzettelBundle\Domain\DaySummary::of($summaryWeek, '2026-02-06', [], $tv)['azvMinutesToDate'],
]);
check('api ping azv', true, in_array('azv', KimaiPlugin\DrehzettelBundle\Domain\ApiInfo::ping(['view' => true, 'manage' => true])['features'], true));

// Engagement form: only a deviation from the default is stored, so a later start date change still applies it.
$edited = azvEngagement('2026-01-05');
$data = EngagementData::fromEngagement($edited);
check('form azv shows effective value', true, $data->azv);
$data->applyTo($edited);
check('form azv default stays automatic', null, $edited->getAzv());
$data->azv = false;
$data->applyTo($edited);
check('form azv off stored', false, $edited->getAzv());
$other = azvEngagement('2026-01-05', Rulesets::quarterHour()->name);
$otherData = EngagementData::fromEngagement($other);
$otherData->azv = true;
$otherData->applyTo($other);
check('form azv on for other ruleset', [true, true], [$other->getAzv(), Azv::eligible($other)]);

// PDF line below the table.
$builder = new TimesheetViewBuilder(stubLabels());
$meta = new TimesheetMeta('X', 'Y', 'Z', 'de', false);
$period = Period::month(2026, 2, new DateTimeZone('Europe/Berlin'));
$view = $builder->build($meta, $period, [$summaryWeek], Rulesets::tvFfs2024(), PdfOptions::defaults(), $full);
check('view: azv line', 'drehzettel.azv.pdf_line(%date%=06.02.2026,%hours%=10:00,%shooting%=25,%days%=drehzettel.azv.days(%count%=1))', $view['azv']);
check('view: no azv line', [null, null], [$builder->build($meta, $period, [$summaryWeek], Rulesets::tvFfs2024(), PdfOptions::defaults(), $quarter)['azv'], $builder->build($meta, $period, [$summaryWeek], Rulesets::tvFfs2024(), PdfOptions::defaults())['azv']]);
