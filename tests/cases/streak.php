<?php

require_once dirname(__DIR__) . '/support.php';

use KimaiPlugin\DrehzettelBundle\Domain\DayInput;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Domain\Streak;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Enum\StreakMode;
use KimaiPlugin\DrehzettelBundle\Service\ConsecutiveDayCounter;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmWeekService;

/*
 * StreakMode CONSECUTIVE (product-owner decisions D-1..D-4, 2026-09-25): counted
 * across ISO weeks, a day without entry resets, travel days count, day 8+ is day 7.
 * StreakMode CALENDAR_WEEK (TV FFS TZ 5.4.3.4, default): n-th entry of the week.
 */

// Kimai's User is stubbed: tests run without Kimai.
if (!class_exists('App\Entity\User')) {
    eval('namespace App\Entity; class User {
        public function getTimezone(): string { return "Europe/Berlin"; }
        public function getDateTimezone(): \DateTimeZone { return new \DateTimeZone("Europe/Berlin"); } }');
}

// Timesheet source for FilmWeekService: fixed inputs, filtered like DayInputBuilder (local date key, validity).
function streakInputs(array $inputs): DayInputBuilder
{
    return new class($inputs) extends DayInputBuilder {
        public int $queries = 0;

        public function __construct(private readonly array $all)
        {
        }

        public function build(Engagement $engagement, DateTimeImmutable $from, DateTimeImmutable $to, array $drafts = []): array
        {
            return array_values(array_filter($this->all, fn (DayInput $i): bool => $this->inside($engagement, $i, $from, $to)));
        }

        public function workedDays(Engagement $engagement, DateTimeImmutable $from, DateTimeImmutable $to): array
        {
            ++$this->queries;
            $days = [];
            foreach ($this->build($engagement, $from, $to) as $input) {
                $days[Streak::key($input->begin)] = $input->productionDay;
            }

            return $days;
        }

        private function inside(Engagement $engagement, DayInput $input, DateTimeImmutable $from, DateTimeImmutable $to): bool
        {
            $key = Streak::key($input->begin);

            return $key >= Streak::key($from) && $key < Streak::key($to) && $key >= Streak::key($engagement->getValidFrom());
        }
    };
}

function streakWeeks(DayInputBuilder $inputs, Ruleset $rules): FilmWeekService
{
    $engagements = new class($rules) extends EngagementService {
        public function __construct(private readonly Ruleset $rules)
        {
        }

        public function ruleset(Engagement $engagement): Ruleset
        {
            return $this->rules;
        }

        public function terms(Engagement $engagement): PayTerms
        {
            return new PayTerms(PayKind::WEEKLY, 158100, 0);
        }
    };

    return new FilmWeekService($inputs, weekCalc(), dayCalc(), $engagements, new ConsecutiveDayCounter($inputs));
}

function streakEngagement(string $validFrom = '2026-01-01'): Engagement
{
    $engagement = new Engagement();
    $engagement->setUser(new App\Entity\User());
    $engagement->setValidFrom(new DateTimeImmutable($validFrom));

    return $engagement;
}

// One 8 h day per date: 'YYYY-MM-DD' or [date, override, type].
function streakDays(array $dates): array
{
    return array_map(static function (string|array $d): DayInput {
        [$date, $override, $type] = is_array($d) ? $d + [1 => null, 2 => DayType::WORKDAY] : [$d, null, DayType::WORKDAY];

        return new DayInput(at($date, '08:00'), at($date, '16:45'), type: $type, breakMinutes: 45, productionDay: $override);
    }, $dates);
}

function dateRange(string $from, int $days): array
{
    return array_map(static fn (int $i): string => (new DateTimeImmutable($from))->modify("+$i days")->format('Y-m-d'), range(0, $days - 1));
}

// N per date of every week in [from, to).
function streakNumbers(FilmWeekService $weeks, Engagement $engagement, string $from, string $to): array
{
    $numbers = [];
    foreach ($weeks->period($engagement, new DateTimeImmutable($from), new DateTimeImmutable($to)) as $week) {
        foreach ($week->days as $day) {
            $numbers[Streak::key($day->begin)] = $day->dayNumber;
        }
    }

    return $numbers;
}

$app = withMode(Rulesets::quarterHour(), StreakMode::CONSECUTIVE);
$tv = withMode(Rulesets::tvFfs2024(), StreakMode::CONSECUTIVE);
$engagement = streakEngagement();

// Defaults: every preset and every stored ruleset without the key counts in the calendar week.
$legacy = RulesetCodec::toArray(Rulesets::tvFfs2024());
unset($legacy['streakMode']);
check('streak mode defaults', [StreakMode::CALENDAR_WEEK, StreakMode::CALENDAR_WEEK, StreakMode::CALENDAR_WEEK], [Rulesets::tvFfs2024()->streakMode, Rulesets::quarterHour()->streakMode, RulesetCodec::fromArray($legacy)->streakMode]);
check('streak mode round trip', StreakMode::CONSECUTIVE, $app->streakMode);
check('streak mode max day', [7, 999], [StreakMode::CALENDAR_WEEK->maxDay(), StreakMode::CONSECUTIVE->maxDay()]);

// Keys: plain calendar arithmetic, also over DST and year ends.
check('streak keys', ['2026-03-28', '2026-03-30', '2026-10-26', '2027-01-01'], [Streak::previousKey('2026-03-29'), Streak::nextKey('2026-03-29'), Streak::nextKey('2026-10-25'), Streak::nextKey('2026-12-31')]);

// Wed 17 - Tue 23 June 2026: Monday of the next ISO week is day 6, Tuesday day 7.
$wedToTue = streakDays(dateRange('2026-06-17', 7));
$weeks = streakWeeks(streakInputs($wedToTue), $app);
check('streak across week', ['2026-06-17' => 1, '2026-06-18' => 2, '2026-06-19' => 3, '2026-06-20' => 4, '2026-06-21' => 5, '2026-06-22' => 6, '2026-06-23' => 7], streakNumbers($weeks, $engagement, '2026-06-15', '2026-06-29'));
$week26 = $weeks->week($engagement, 2026, 26);
check('streak week lookback', [6, 7], [$week26->days[0]->dayNumber, $week26->days[1]->dayNumber]);
check('streak 6th/7th surcharge', [2500, 5000], [$week26->days[0]->dayCountShare?->basisPoints, $week26->days[1]->dayCountShare?->basisPoints]);

// TV FFS: day 6 and 7 go into the weekly pool, even in a short week.
$tvWeek = streakWeeks(streakInputs($wedToTue), $tv)->week($engagement, 2026, 26);
check('streak tv pool', 960, $tvWeek->weeklyPoolMinutes);

// A day without entry resets: Mon 22 off, Tue 23 is day 1 again.
$gap = streakDays([...dateRange('2026-06-17', 5), '2026-06-23']);
check('streak gap resets', [1], array_map(static fn ($d): int => $d->dayNumber, streakWeeks(streakInputs($gap), $app)->week($engagement, 2026, 26)->days));

// Travel days count as a day in the row (and stay without surcharges).
$travel = streakDays([...dateRange('2026-06-17', 4), ['2026-06-21', null, DayType::TRAVEL], '2026-06-22']);
$travelWeek = streakWeeks(streakInputs($travel), $app)->week($engagement, 2026, 26);
check('streak travel counts', [6, 2500], [$travelWeek->days[0]->dayNumber, $travelWeek->days[0]->dayCountShare?->basisPoints]);

// Day 8 and 9 are treated like day 7: fixed 50 %, TV FFS pools them.
$nine = streakDays(dateRange('2026-06-15', 9));
$nineWeek = streakWeeks(streakInputs($nine), $app)->week($engagement, 2026, 26);
check('streak day 8/9', [8, 9, 5000, 5000], [$nineWeek->days[0]->dayNumber, $nineWeek->days[1]->dayNumber, $nineWeek->days[0]->dayCountShare?->basisPoints, $nineWeek->days[1]->dayCountShare?->basisPoints]);
check('streak tv day 8/9 pooled', 960, streakWeeks(streakInputs($nine), $tv)->week($engagement, 2026, 26)->weeklyPoolMinutes);

// Override: the day is N, following days continue from it; in the lookback, too.
$override = streakDays(['2026-06-18', ['2026-06-19', 4], '2026-06-20', '2026-06-21', '2026-06-22']);
$overrideWeeks = streakWeeks(streakInputs($override), $app);
check('streak override continues', ['2026-06-18' => 1, '2026-06-19' => 4, '2026-06-20' => 5, '2026-06-21' => 6, '2026-06-22' => 7], streakNumbers($overrideWeeks, $engagement, '2026-06-15', '2026-06-29'));
check('streak override in lookback', 7, $overrideWeeks->week($engagement, 2026, 26)->days[0]->dayNumber);
$overridden = $overrideWeeks->week($engagement, 2026, 25)->days;
check('streak override kept on result', [null, 4], [$overridden[0]->productionDay, $overridden[1]->productionDay]);

// Pure numbering: an override of 1 restarts; a lower override wins over the count.
check('streak numbers', [3, 4, 1, 2], Streak::numbers(streakDays(['2026-06-17', '2026-06-18', ['2026-06-19', 1], '2026-06-20']), 2, StreakMode::CONSECUTIVE));

// DST: Europe/Berlin springs forward on Sun 29 March 2026 and falls back on Sun 25 October.
$spring = streakDays(dateRange('2026-03-25', 7));
check('streak dst spring', [6, 7], array_map(static fn ($d): int => $d->dayNumber, streakWeeks(streakInputs($spring), $app)->week($engagement, 2026, 14)->days));
$autumn = streakDays(dateRange('2026-10-21', 7));
check('streak dst autumn', [6, 7], array_map(static fn ($d): int => $d->dayNumber, streakWeeks(streakInputs($autumn), $app)->week($engagement, 2026, 44)->days));

// An entry from Sat 20:00 to Sun 06:00 belongs to Saturday only: Sunday is a gap.
$night = [shift('2026-06-19', '08:00', '16:00'), shift('2026-06-20', '20:00', '06:00'), shift('2026-06-22', '08:00', '16:00')];
check('streak midnight entry', ['2026-06-19' => 1, '2026-06-20' => 2, '2026-06-22' => 1], streakNumbers(streakWeeks(streakInputs($night), $app), $engagement, '2026-06-15', '2026-06-29'));
$nightToMonday = [shift('2026-06-20', '08:00', '16:00'), shift('2026-06-21', '22:00', '06:00'), shift('2026-06-22', '20:00', '23:00')];
check('streak midnight entry into monday', 3, streakWeeks(streakInputs($nightToMonday), $app)->week($engagement, 2026, 26)->days[0]->dayNumber);

// Lookback reaches further windows only for long rows, and stops at the engagement start.
$long = streakInputs(streakDays(dateRange('2026-06-01', 30)));
$counter = new ConsecutiveDayCounter($long);
check('streak long lookback', [29, 3], [$counter->before($engagement, new DateTimeImmutable('2026-06-30 08:00')), $long->queries]);
$short = streakInputs(streakDays(dateRange('2026-06-01', 30)));
check('streak short lookback', [4, 1], [(new ConsecutiveDayCounter($short))->before($engagement, new DateTimeImmutable('2026-06-05')), $short->queries]);
check('streak engagement start', 4, (new ConsecutiveDayCounter(streakInputs(streakDays(dateRange('2026-06-01', 30)))))->before(streakEngagement('2026-06-10'), new DateTimeImmutable('2026-06-14')));
check('streak day before not worked', 0, (new ConsecutiveDayCounter(streakInputs(streakDays(['2026-06-01']))))->before($engagement, new DateTimeImmutable('2026-06-03')));

// Calendar week: Wed-Tue makes Mon/Tue day 1/2 of the new week, without any lookback query.
$calendar = streakInputs($wedToTue);
$calendarWeek = streakWeeks($calendar, Rulesets::quarterHour())->week($engagement, 2026, 26);
check('calendar week restarts', [1, 2, null, 0], [$calendarWeek->days[0]->dayNumber, $calendarWeek->days[1]->dayNumber, $calendarWeek->days[0]->dayCountShare, $calendar->queries]);

// Mon, Tue, (Wed off), Thu-Sun: the calendar week makes Sunday day 6 (TV FFS), consecutive day 4.
$offWednesday = streakDays(['2026-06-15', '2026-06-16', ...dateRange('2026-06-18', 4)]);
check('calendar week counts over a gap', [6, 4], [
    streakWeeks(streakInputs($offWednesday), Rulesets::tvFfs2024())->week($engagement, 2026, 25)->days[5]->dayNumber,
    streakWeeks(streakInputs($offWednesday), $tv)->week($engagement, 2026, 25)->days[5]->dayNumber,
]);

// Calendar week override: its own day only, as before StreakMode existed.
check('calendar week override', [1, 6, 3], array_map(static fn ($d): int => $d->dayNumber, weekCalc()->calc(streakDays(['2026-06-15', ['2026-06-16', 6], '2026-06-17']), Rulesets::quarterHour(), null)->days));
