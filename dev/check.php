<?php

/*
 * Dev integration check, after seed.php:
 * - two users on one project with different rulesets
 * - overlapping engagement is rejected
 * - period() splits by ISO week
 * Run: docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/check.php
 */

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Kernel;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Repository\TimesheetRangeRepository;
use KimaiPlugin\DrehzettelBundle\Service\DayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmWeekService;
use KimaiPlugin\DrehzettelBundle\Service\NullHolidayLookup;
use KimaiPlugin\DrehzettelBundle\Service\PayCalculator;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
use KimaiPlugin\DrehzettelBundle\Service\WeekCalculator;

require '/opt/kimai/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/opt/kimai/.env');
$kernel = new Kernel('prod', false);
$kernel->boot();
$registry = $kernel->getContainer()->get('doctrine');
$em = $registry->getManager();

$fails = 0;
function expect(string $name, mixed $expected, mixed $actual): void
{
    global $fails;
    $ok = $expected === $actual;
    $fails += $ok ? 0 : 1;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok ? '' : ' expected ' . json_encode($expected) . ' got ' . json_encode($actual)) . "\n";
}

$project = $em->getRepository(Project::class)->findOneBy(['name' => 'Sample Film (dev)']);
$admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);
$crew = $em->getRepository(User::class)->findOneBy(['username' => 'crew2']);
$activity = $em->getRepository(Activity::class)->findOneBy(['project' => $project]);
$zone = new DateTimeZone('Europe/Berlin');

$engagements = new EngagementRepository($registry);
$service = new EngagementService($engagements, new RulesetCatalog(new FilmRulesetRepository($registry)));
$pay = new PayCalculator();
$dayCalc = new DayCalculator($pay);
$weeks = new FilmWeekService(
    new DayInputBuilder(new TimesheetRangeRepository($registry), new FilmDayRepository($registry), new NullHolidayLookup()),
    new WeekCalculator($dayCalc, $pay),
    $dayCalc,
    $service,
);

// Overlap is rejected.
try {
    $service->open($admin, $project, 'x', new PayTerms(PayKind::WEEKLY, 1), new DateTimeImmutable('2025-06-02'), null, RulesetCatalog::TV_FFS_2024);
    expect('overlap rejected', true, false);
} catch (DomainException) {
    expect('overlap rejected', true, true);
}

// Second person: same times as admin on Tue 19 May, TV FFS rules.
$engagement = $service->active($crew, $project, new DateTimeImmutable('2025-05-20'))
    ?? $service->open($crew, $project, 'Oberbeleuchter', new PayTerms(PayKind::WEEKLY, 170000, 0), new DateTimeImmutable('2025-05-02'), null, RulesetCatalog::TV_FFS_2024);
if ($em->getRepository(Timesheet::class)->findOneBy(['user' => $crew]) === null) {
    $sheet = new Timesheet();
    $sheet->setUser($crew);
    $sheet->setActivity($activity);
    $sheet->setProject($project);
    $sheet->setBegin(new DateTime('2025-05-20 08:30', $zone));
    $sheet->setEnd(new DateTime('2025-05-20 20:15', $zone));
    $em->persist($sheet);
    $em->flush();
}

$mine = $weeks->week($engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20')), 2025, 21);
$theirs = $weeks->week($engagement, 2025, 21);
expect('admin tier minutes Tue', [60, 45, 0], array_map(fn ($s) => $s->minutes, $mine->days[1]->dailyShares));
expect('crew2 tier minutes Tue (TV FFS, 11:00 h work)', [60, 0], array_map(fn ($s) => $s->minutes, $theirs->days[0]->dailyShares));
expect('crew2 break default 45 min, excess rule', 45, $theirs->days[0]->breakMinutes);
expect('crew2 work 11:00 (no break entered -> default 45)', 660, $theirs->days[0]->workMinutes);
expect('snapshot names differ', ['Quarter-Hour Ruleset', 'TV FFS 2024'], [$engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20'))->getRulesetName(), $engagement->getRulesetName()]);

// Activity restriction: crew2's engagement counts only $activity. A same-project
// "Anfahrt" (commute) entry on a different activity must not be detected as film time.
$commuteActivity = $em->getRepository(Activity::class)->findOneBy(['name' => 'Anfahrt (dev)']);
if ($commuteActivity === null) {
    $commuteActivity = new Activity();
    $commuteActivity->setName('Anfahrt (dev)');
    $commuteActivity->setProject($project);
    $em->persist($commuteActivity);
    $em->flush();
}
$commuteSheet = $em->getRepository(Timesheet::class)->findOneBy(['user' => $crew, 'activity' => $commuteActivity]);
if ($commuteSheet === null) {
    $commuteSheet = new Timesheet();
    $commuteSheet->setUser($crew);
    $commuteSheet->setActivity($commuteActivity);
    $commuteSheet->setProject($project);
    $commuteSheet->setBegin(new DateTime('2025-05-20 07:00', $zone));
    $commuteSheet->setEnd(new DateTime('2025-05-20 08:15', $zone));
    $em->persist($commuteSheet);
    $em->flush();
}
expect('unrestricted engagement: commute activity still counts', true, $engagement->appliesToActivity($commuteActivity));
expect('unrestricted engagement: commute entry has an active engagement', true, $service->activeFor($commuteSheet) !== null);

$engagement->setActivityIds([$activity->getId()]);
$service->save($engagement);
expect('restricted engagement: primary activity still counts', true, $engagement->appliesToActivity($activity));
expect('restricted engagement: commute activity excluded', false, $engagement->appliesToActivity($commuteActivity));
expect('restricted engagement: commute entry has no active engagement', true, $service->activeFor($commuteSheet) === null);
$engagement->setActivityIds([]);
$service->save($engagement);

// Period over one week: query range narrowed to week 21 only -> exactly one week result.
// (The seed data spans many weeks of the reference fixtures, not just week 21, so a wider
// range here would legitimately split into several WeekResults - that's not a bug.)
$period = $weeks->period($engagements->findActive($admin, $project, new DateTimeImmutable('2025-05-20')), new DateTimeImmutable('2025-05-19', $zone), new DateTimeImmutable('2025-05-24', $zone));
expect('period weeks', 1, count($period));
expect('period work', 43 * 60 + 15, $period[0]->workMinutes);

echo $fails === 0 ? "All checks passed\n" : "$fails failed\n";
exit($fails === 0 ? 0 : 1);
